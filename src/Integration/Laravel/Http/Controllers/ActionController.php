<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Actions\ActionScope;
use Pepperfm\Flashboard\Contracts\Actions\ContextualActionContract;
use Pepperfm\Flashboard\Contracts\Actions\FormActionContract;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionContext;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutor;
use Pepperfm\Flashboard\Integration\Laravel\Actions\ActionForm;
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

    public function prepare(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        [$action, $context] = $this->resolve($request);
        if (!$action instanceof FormActionContract || !$action->hasForm()) {
            abort(404);
        }
        $form = $action->resolveForm($context->toArray());
        abort_if($form === null, 403);

        return response()->json(new ActionForm($form)->payload())
            ->header('Cache-Control', 'private, no-store');
    }

    public function __invoke(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->execute($request);
    }

    public function execute(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($request->isMethod('POST'), 405);
        [$resolvedAction, $actionContext] = $this->resolve($request);
        $context = $actionContext->toArray();
        $hasForm = $resolvedAction instanceof FormActionContract && $resolvedAction->hasForm();
        $explicitScope = $resolvedAction instanceof ContextualActionContract
            && $resolvedAction->actionScope() !== ActionScope::Legacy;
        $form = $hasForm ? $resolvedAction->resolveForm($context) : null;
        abort_if($hasForm && $form === null, 403);
        if (!$hasForm && !$explicitScope) {
            $context['record'] = $request->route('record');
        }

        $context['payload'] = $request->all();
        if ($form !== null) {
            $input = $request->validate([
                'payload' => ['present', 'array'],
                'idempotency_key' => ['required', 'uuid'],
            ]);
            $context['payload'] = new ActionForm($form)->validated($input['payload']);
            $context['idempotency_key'] = $input['idempotency_key'];
        } elseif ($explicitScope) {
            $input = $request->validate([
                'payload' => ['sometimes', 'array', 'size:0'],
                'idempotency_key' => ['sometimes', 'uuid'],
            ]);
            $context['payload'] = [];
            $context['idempotency_key'] = Arr::get($input, 'idempotency_key');
        }

        $arguments = $explicitScope || $hasForm ? $context : Arr::only($context, ['record', 'payload']);
        try {
            $result = $this->actionExecutor->execute($resolvedAction, $arguments)->toArray();
        } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable) {
            logger()->error('Flashboard action execution failed.');

            if ($request->expectsJson()) {
                return response()->json([
                    'successful' => false,
                    'message' => 'Action could not be completed.',
                    'redirect_to' => null,
                    'data' => [],
                ], 500);
            }

            return redirect()->back()->with('error', 'Action could not be completed.');
        }

        if ($request->expectsJson()) {
            return response()->json($result, $result['successful'] ? 200 : 409);
        }

        $redirect = Arr::get($result, 'redirect_to');
        $response = $result['successful'] && is_string($redirect) && $redirect !== ''
            ? redirect()->to($redirect)
            : back();
        $message = Arr::get($result, 'message') ?? ($explicitScope || $hasForm
            ? ($result['successful'] ? 'Action completed.' : 'Action could not be completed.')
            : null);
        if (is_string($message) && $message !== '') {
            $response->with($result['successful'] ? 'success' : 'error', $message);
        }

        return $response;
    }

    /** @return array{ActionContract, ActionContext} */
    private function resolve(\Illuminate\Http\Request $request): array
    {
        $resourceClass = $request->route()?->defaults['flashboard.resource'] ?? null;
        abort_unless(is_string($resourceClass) && is_subclass_of($resourceClass, \Pepperfm\Flashboard\Contracts\Resources\Resource::class), 404);

        $user = $this->authenticator->user();
        $recordKey = $request->route('record');
        $action = $request->route('action');
        abort_unless(is_string($action), 404);
        $record = null;
        if ($recordKey === null) {
            // Collection actions have no record policy to fall back to.
            abort_unless($this->screenAccessResolver->canAccessResource($resourceClass, $user), 403);
        } else {
            try {
                $record = $resourceClass::resolveRecord($recordKey);
            } catch (\Illuminate\Database\QueryException $exception) {
                if ($exception->getCode() === '22P02' && str_contains($exception->getMessage(), 'invalid input syntax for type uuid')) {
                    abort(404);
                }

                throw $exception;
            }
            abort_unless($record instanceof \Illuminate\Database\Eloquent\Model, 404);
            abort_unless($this->screenAccessResolver->canViewRecord($resourceClass, $user, $record), 403);
        }

        $resolvedAction = $this->resourceSurfaceResolver->findAction($resourceClass, $action);
        abort_unless($resolvedAction instanceof ActionContract, 404);

        if (!$this->screenAccessResolver->canExecuteAction($resourceClass, $resolvedAction, $user, $record)) {
            abort(403);
        }

        return [$resolvedAction, new ActionContext($resourceClass, $user, $record)];
    }
}
