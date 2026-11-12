<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutor;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class ActionController
{
    public function __construct(
        private ActionExecutor $actionExecutor,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    public function __invoke(
        \Illuminate\Http\Request $request,
        string $action
    ): \Symfony\Component\HttpFoundation\Response {
        $resourceClass = $request->route()?->defaults['flashboard.resource'] ?? null;
        abort_unless(is_string($resourceClass) && is_subclass_of($resourceClass, \Pepperfm\Flashboard\Contracts\Resources\Resource::class), 404);

        $user = $this->authenticator->user();
        $recordKey = $request->route('record');
        if ($recordKey === null) {
            // Collection actions have no record policy to fall back to.
            abort_unless($this->screenAccessResolver->canAccessResource($resourceClass, $user), 403);
        } else {
            $record = $resourceClass::resolveRecord($recordKey);
            abort_unless($record instanceof \Illuminate\Database\Eloquent\Model, 404);
            abort_unless($this->screenAccessResolver->canViewRecord($resourceClass, $user, $record), 403);
        }

        $resolvedAction = $this->resourceSurfaceResolver->findAction($resourceClass, $action);
        abort_unless($resolvedAction instanceof ActionContract, 404);

        if (!$this->screenAccessResolver->canViewAction($resourceClass, $resolvedAction->key(), $user)) {
            abort(403);
        }

        $result = $this->actionExecutor->execute($resolvedAction, [
            'record' => $request->route('record'),
            'payload' => $request->all(),
        ])->toArray();

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if (is_string($result['redirect_to'] ?? null) && $result['redirect_to'] !== '') {
            return redirect()->to($result['redirect_to']);
        }

        return redirect()->back();
    }
}
