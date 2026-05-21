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
        abort_unless(is_string($resourceClass) && $resourceClass !== '', 404);

        $user = $this->authenticator->user();
        $record = $resourceClass::resolveRecord($request->route('record'));
        if ($record !== null && !$this->screenAccessResolver->canViewRecord($resourceClass, $user, $record)) {
            abort(403);
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
