<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;

final readonly class ResourceFormController
{
    public function __construct(
        private ResourceFormPersister $persister,
        private PanelAuthenticator $authenticator,
        private ScreenAccessResolver $screenAccessResolver,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $resourceClass = $this->resourceClass($request);
        $user = $this->authenticator->user();

        if (!$this->screenAccessResolver->canCreateRecord($resourceClass, $user)) {
            logger()->warning('[FIX] Denied resource create submission.', [
                'resource' => $resourceClass,
            ]);
            abort(403);
        }

        $data = $request->validate($resourceClass::creationRules());
        $record = $this->persister->create($resourceClass, $data);

        return $this->redirectAfterSave($resourceClass, $record);
    }

    public function update(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $resourceClass = $this->resourceClass($request);
        $record = $resourceClass::resolveRecord($request->route('record'));
        abort_unless($record instanceof Model, 404);
        $user = $this->authenticator->user();

        if (!$this->screenAccessResolver->canEditRecord($resourceClass, $user, $record)) {
            logger()->warning('[FIX] Denied resource update submission.', [
                'resource' => $resourceClass,
                'record' => $record->getKey(),
            ]);
            abort(403);
        }

        $data = $request->validate($resourceClass::updateRules($record));
        $this->persister->update($resourceClass, $record, $data);

        return $this->redirectAfterSave($resourceClass, $record);
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
    private function redirectAfterSave(string $resourceClass, Model $record): \Illuminate\Http\RedirectResponse
    {
        if ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass)) {
            return redirect()->route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.detail',
                ['record' => $record->getKey()],
            );
        }

        return redirect()->route(
            config('flashboard.route_name_prefix', 'flashboard.')
            . 'resources.' . $resourceClass::key() . '.index',
        );
    }
}
