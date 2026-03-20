<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutor;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class ActionController
{
    public function __construct(
        private ActionExecutor $actionExecutor,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
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
            logger()->warning('[FIX] Denied resource action on inaccessible record.', [
                'action' => $action,
                'resource' => $resourceClass,
            ]);
            abort(403);
        }

        if (!$this->screenAccessResolver->canViewAction($resourceClass, $action, $user)) {
            logger()->warning('[FIX] Denied resource action by action visibility rule.', [
                'action' => $action,
                'resource' => $resourceClass,
            ]);
            abort(403);
        }

        $resolvedAction = $this->resolveAction($resourceClass, $action);
        abort_unless($resolvedAction instanceof ActionContract, 404);

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

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function resolveAction(string $resourceClass, string $actionKey): ?ActionContract
    {
        foreach ($resourceClass::actions() as $action) {
            if ($action->key() === $actionKey) {
                return $action;
            }
        }

        return null;
    }
}
