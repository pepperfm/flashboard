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

    public function attach(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, 'attach');
    }

    public function detach(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, 'detach');
    }

    public function replace(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, 'replace');
    }

    public function sync(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        return $this->run($request, 'sync');
    }

    private function run(\Illuminate\Http\Request $request, string $action): \Illuminate\Http\RedirectResponse
    {
        $relation = $request->route('relation');
        abort_unless(is_string($relation), 404);

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
        } catch (\Illuminate\Validation\ValidationException $e) {
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
            $expected = $request->input('expected_related');
            if ($request->has('expected_related') && (!is_array($expected) || !array_is_list($expected) || array_any($expected, static fn (mixed $key): bool => !is_scalar($key)))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['related' => ['Invalid relation snapshot.']]);
            }

            $this->persister->sync(
                $resourceClass, $record, $relation, $this->relatedKeys($request), $user,
                expectedRelatedKeys: is_array($expected) ? $expected : null,
            );
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
