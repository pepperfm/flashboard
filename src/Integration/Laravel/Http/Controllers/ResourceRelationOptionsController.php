<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationOptionsDataSource;

final readonly class ResourceRelationOptionsController
{
    public function __construct(
        private ResourceRelationOptionsDataSource $dataSource,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
    ) {
    }

    public function __invoke(\Illuminate\Http\Request $request, string $field): \Illuminate\Http\JsonResponse
    {
        $resourceClass = $request->route()?->defaults['flashboard.resource'] ?? null;
        if (!is_string($resourceClass) || !is_subclass_of($resourceClass, Resource::class)) {
            abort(404);
        }
        if (!$this->screenAccessResolver->canAccessResource($resourceClass, $this->authenticator->user())) {
            abort(403);
        }

        try {
            return response()->json($this->dataSource->resolve($resourceClass, $field, $request));
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            logger()->warning('BelongsTo relation options request was rejected.', [
                'resource' => $resourceClass,
                'field' => $field,
                'failure' => 'not_found',
            ]);

            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            logger()->warning('BelongsTo relation options request was rejected.', [
                'resource' => $resourceClass,
                'field' => $field,
                'failure' => 'access_denied',
            ]);

            throw $e;
        } catch (\InvalidArgumentException $e) {
            logger()->warning('BelongsTo relation options request was rejected.', [
                'resource' => $resourceClass,
                'field' => $field,
                'failure' => 'invalid_configuration',
                'exception' => $e::class,
            ]);

            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(previous: $e);
        } catch (\Throwable $e) {
            logger()->error('BelongsTo relation options request failed.', [
                'resource' => $resourceClass,
                'field' => $field,
                'exception' => $e::class,
                'sql_state' => Arr::get($e instanceof \Illuminate\Database\QueryException ? $e->errorInfo : [], 0),
            ]);

            throw $e;
        }
    }
}
