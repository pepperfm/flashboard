<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationCreateContext;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationCreateContextResolver;

final readonly class ResourceFormController
{
    public function __construct(
        private ResourceFormPersister $persister,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
        private ?ResourceRegistry $resourceRegistry = null,
    ) {
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $resourceClass = $this->resourceClass($request);
        $user = $this->authenticator->user();

        if (!$this->screenAccessResolver->canCreateRecord($resourceClass, $user)) {
            abort(403);
        }

        $relationCreateContext = $this->relationCreateContext($request, $resourceClass);
        if ($relationCreateContext !== null) {
            if (!$this->screenAccessResolver->canViewRecord(
                $relationCreateContext->parentResource,
                $user,
                $relationCreateContext->parentRecord,
            )) {
                abort(403);
            }

            $request->merge($this->relationCreateContextPayload($relationCreateContext));
        }

        $data = $request->validate($resourceClass::creationRules());

        if ($relationCreateContext !== null) {
            $data = array_merge($data, $this->relationCreateContextPayload($relationCreateContext));
        }

        try {
            $record = $this->persister->create($resourceClass, $data);
        } catch (\Illuminate\Database\QueryException $exception) {
            return $this->redirectBackAfterSaveFailure($exception, $resourceClass);
        }

        return $this->redirectAfterSave($resourceClass, $record, $relationCreateContext)
            ->with('success', 'Record saved successfully.');
    }

    public function update(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $resourceClass = $this->resourceClass($request);
        $record = $resourceClass::resolveRecord($request->route('record'));
        abort_unless($record instanceof Model, 404);
        $user = $this->authenticator->user();

        if (!$this->screenAccessResolver->canEditRecord($resourceClass, $user, $record)) {
            abort(403);
        }

        $data = $request->validate($resourceClass::updateRules($record));

        try {
            $this->persister->update($resourceClass, $record, $data);
        } catch (\Illuminate\Database\QueryException $exception) {
            return $this->redirectBackAfterSaveFailure($exception, $resourceClass, $record);
        }

        return $this->redirectAfterSave($resourceClass, $record)
            ->with('success', 'Record saved successfully.');
    }

    public function destroy(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $resourceClass = $this->resourceClass($request);
        $record = $resourceClass::resolveRecord($request->route('record'));
        abort_unless($record instanceof Model, 404);
        $user = $this->authenticator->user();

        if (!$this->screenAccessResolver->canDeleteRecord($resourceClass, $user, $record)) {
            abort(403);
        }

        try {
            $this->persister->delete($resourceClass, $record);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (!$this->isIntegrityConstraintViolation($exception)) {
                throw $exception;
            }

            logger()->warning('Resource record delete was blocked by database constraints.', [
                'resource' => $resourceClass,
                'record' => $record->getKey(),
                'sql_state' => \Illuminate\Support\Arr::get($exception->errorInfo ?? [], 0),
            ]);

            return $this->redirectToResourceIndex($resourceClass)
                ->with('error', 'Record could not be deleted because related records still reference it.');
        }

        return $this->redirectToResourceIndex($resourceClass)
            ->with('success', 'Record deleted successfully.');
    }

    /**
     * @return class-string<Resource>
     */
    private function resourceClass(\Illuminate\Http\Request $request): string
    {
        $resourceClass = $request->route()?->defaults['flashboard.resource'] ?? null;
        abort_unless(is_string($resourceClass) && $resourceClass !== '', 404);

        return $resourceClass;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function redirectAfterSave(
        string $resourceClass,
        Model $record,
        ?RelationCreateContext $relationCreateContext = null,
    ): \Illuminate\Http\RedirectResponse
    {
        if ($relationCreateContext !== null) {
            return $this->redirectToParentAfterRelationCreate($relationCreateContext);
        }

        if ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass)) {
            return redirect()->route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.detail',
                ['record' => $record->getKey()],
            );
        }

        return $this->redirectToResourceIndex($resourceClass);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function relationCreateContext(\Illuminate\Http\Request $request, string $resourceClass): ?RelationCreateContext
    {
        if ($this->resourceRegistry === null) {
            return null;
        }

        return new RelationCreateContextResolver($this->resourceRegistry)->resolve($request, $resourceClass);
    }

    /**
     * @return array<string, mixed>
     */
    private function relationCreateContextPayload(RelationCreateContext $context): array
    {
        return [
            $context->metadata->foreignKey => $context->parentRecord->getAttribute($context->metadata->localKey),
        ];
    }

    private function redirectToParentAfterRelationCreate(RelationCreateContext $context): \Illuminate\Http\RedirectResponse
    {
        if ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($context->parentResource)) {
            return to_route(
                config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $context->parentResource::key() . '.detail',
                ['record' => $context->parentRecord->getKey()],
            );
        }

        return to_route(
            config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $context->parentResource::key() . '.edit',
            ['record' => $context->parentRecord->getKey()],
        );
    }

    private function isIntegrityConstraintViolation(\Illuminate\Database\QueryException $exception): bool
    {
        $sqlState = \Illuminate\Support\Arr::get($exception->errorInfo ?? [], 0);

        return $sqlState === '23000' || str_starts_with((string) $exception->getCode(), '23');
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function redirectBackAfterSaveFailure(
        \Illuminate\Database\QueryException $exception,
        string $resourceClass,
        ?Model $record = null,
    ): \Illuminate\Http\RedirectResponse
    {
        if (!$this->isIntegrityConstraintViolation($exception)) {
            throw $exception;
        }

        logger()->warning('Resource record save was blocked by database constraints.', [
            'resource' => $resourceClass,
            'record' => $record?->getKey(),
            'sql_state' => \Illuminate\Support\Arr::get($exception->errorInfo ?? [], 0),
        ]);

        return redirect()->back()
            ->withInput()
            ->with('error', 'Record could not be saved because the submitted values conflict with existing data.');
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function redirectToResourceIndex(string $resourceClass): \Illuminate\Http\RedirectResponse
    {
        return to_route(
            config('flashboard.route_name_prefix', 'flashboard.') . 'resources.' . $resourceClass::key() . '.index',
        );
    }
}
