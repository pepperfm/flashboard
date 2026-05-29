<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceRelationManagerPersister;

final readonly class ResourceRelationActionController
{
    public function __construct(
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        private ResourceRelationManagerPersister $persister,
    ) {
    }

    public function attach(\Illuminate\Http\Request $request, string $relation): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, $relation, 'attach');
    }

    public function detach(\Illuminate\Http\Request $request, string $relation): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, $relation, 'detach');
    }

    public function replace(\Illuminate\Http\Request $request, string $relation): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, $relation, 'replace');
    }

    public function sync(\Illuminate\Http\Request $request, string $relation): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, $relation, 'sync');
    }

    private function run(\Illuminate\Http\Request $request, string $relation, string $action): \Illuminate\Http\RedirectResponse
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
            $this->dispatch($request, $resourceClass, $record, $relation, $action);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            logger()->warning('Relation manager mutation was rejected.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => $action,
                'failure' => 'not_found',
            ]);

            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            logger()->warning('Relation manager mutation was rejected.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => $action,
                'failure' => 'access_denied',
            ]);

            throw $e;
        } catch (\InvalidArgumentException $e) {
            logger()->warning('Relation manager mutation was rejected.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => $action,
                'failure' => 'invalid_configuration',
                'exception' => $e::class,
            ]);

            return back()->with('error', 'Relation action is not available.');
        } catch (\Throwable $e) {
            logger()->error('Relation manager mutation failed.', [
                'resource' => $resourceClass,
                'relation' => $relation,
                'action' => $action,
                'exception' => $e::class,
                'sql_state' => Arr::get($e instanceof \Illuminate\Database\QueryException ? $e->errorInfo : [], 0),
            ]);

            throw $e;
        }

        return back()->with('success', 'Relation updated successfully.');
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function dispatch(
        \Illuminate\Http\Request $request,
        string $resourceClass,
        Model $record,
        string $relation,
        string $action,
    ): void {
        $user = $this->authenticator->user();

        if ($action === 'attach') {
            $this->persister->attach($resourceClass, $record, $relation, $this->singleRelatedKey($request), $user);
            return;
        }
        if ($action === 'detach') {
            $this->persister->detach($resourceClass, $record, $relation, $this->relatedKeys($request), $user);
            return;
        }
        if ($action === 'replace') {
            $this->persister->replace($resourceClass, $record, $relation, $this->singleRelatedKey($request), $user);
            return;
        }
        if ($action === 'sync') {
            $this->persister->sync($resourceClass, $record, $relation, $this->relatedKeys($request), $user);
        }
    }

    private function singleRelatedKey(\Illuminate\Http\Request $request): mixed
    {
        return $request->input('related', $request->input('record'));
    }

    /**
     * @return list<mixed>
     */
    private function relatedKeys(\Illuminate\Http\Request $request): array
    {
        $values = $request->input('related', $request->input('records', []));

        return array_values(is_array($values) ? $values : [$values]);
    }
}
