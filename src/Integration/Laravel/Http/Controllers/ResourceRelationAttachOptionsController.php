<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceRelationAttachOptionsDataSource;

final readonly class ResourceRelationAttachOptionsController
{
    public function __construct(
        private ResourceRelationAttachOptionsDataSource $dataSource,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
    ) {
    }

    public function __invoke(\Illuminate\Http\Request $request, string $relation): \Illuminate\Http\JsonResponse
    {
        $resourceClass = $request->route()?->defaults['flashboard.resource'] ?? null;
        if (!is_string($resourceClass) || !is_subclass_of($resourceClass, Resource::class)) {
            abort(404);
        }

        $record = $resourceClass::resolveRecord($request->route('record'));
        abort_unless($record instanceof Model, 404);

        if (!$this->screenAccessResolver->canViewRecord($resourceClass, $this->authenticator->user(), $record)) {
            abort(403);
        }

        try {
            return response()->json($this->dataSource->resolve($resourceClass, $record, $relation, $request));
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            logger()->warning('Relation manager attach options request was rejected.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => 'options',
                'failure' => 'not_found',
            ]);

            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            logger()->warning('Relation manager attach options request was rejected.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => 'options',
                'failure' => 'access_denied',
            ]);

            throw $e;
        } catch (\InvalidArgumentException $e) {
            logger()->warning('Relation manager attach options request was rejected.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => 'options',
                'failure' => 'invalid_configuration',
                'exception' => $e::class,
            ]);

            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(previous: $e);
        } catch (\Throwable $e) {
            logger()->error('Relation manager attach options request failed.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => 'options',
                'exception' => $e::class,
                'sql_state' => Arr::get($e instanceof \Illuminate\Database\QueryException ? $e->errorInfo : [], 0),
            ]);

            throw $e;
        }
    }
}
