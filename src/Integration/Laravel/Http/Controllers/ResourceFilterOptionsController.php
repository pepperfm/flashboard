<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFilterOptionsDataSource;

final readonly class ResourceFilterOptionsController
{
    public function __construct(
        private ResourceFilterOptionsDataSource $dataSource,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
    ) {
    }

    public function __invoke(\Illuminate\Http\Request $request, string $filter): \Illuminate\Http\JsonResponse
    {
        $resourceClass = $request->route()?->defaults['flashboard.resource'] ?? null;

        if (!is_string($resourceClass) || !is_subclass_of($resourceClass, Resource::class)) {
            abort(404);
        }

        if (!$this->screenAccessResolver->canAccessResource($resourceClass, $this->authenticator->user())) {
            abort(403);
        }

        try {
            return response()->json($this->dataSource->resolve($resourceClass, $filter, $request));
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception) {
            logger()->warning('Lazy select filter options request was rejected.', [
                'resource' => $resourceClass,
                'filter' => $filter,
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            logger()->error('Lazy select filter options request failed.', [
                'resource' => $resourceClass,
                'filter' => $filter,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
