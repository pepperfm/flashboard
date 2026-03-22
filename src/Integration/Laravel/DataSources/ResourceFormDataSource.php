<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Resources\ResourceSurfaceResolver;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class ResourceFormDataSource
{
    public function __construct(
        private FormPayloadAssembler $formPayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
        private ResourceSurfaceResolver $resourceSurfaceResolver,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, ?Model $record = null): array
    {
        $form = $resourceClass::form(Form::make());
        $schema = $this->formPayloadAssembler->assemble($resourceClass);
        $state = $form->defaultState();
        $user = $this->authenticator->user();

        if ($record !== null) {
            foreach ($schema->fields() as $field) {
                $key = (string) $field['key'];
                if ($key === '') {
                    continue;
                }
                if (!$this->screenAccessResolver->canViewField($resourceClass, $key, $user)) {
                    continue;
                }

                $state[$key] = data_get($record, $key);
            }
        }

        $fields = array_values(array_filter(
            $schema->fields(),
            fn(array $field): bool => $this->screenAccessResolver->canViewField(
                $resourceClass,
                (string) $field['key'],
                $user,
            ),
        ));
        $cancelUrl = null;

        if ($record === null) {
            $cancelUrl = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.index',
            );
        } elseif ($this->resourceSurfaceResolver->hasDetailSurfaceForResource($resourceClass)) {
            $cancelUrl = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.detail',
                ['record' => $record->getKey()],
            );
        } else {
            $cancelUrl = route(
                config('flashboard.route_name_prefix', 'flashboard.')
                . 'resources.' . $resourceClass::key() . '.index',
            );
        }

        $payload = array_merge($schema->toArray(), [
            'fields' => $fields,
            'state' => $form->mutateData($state),
            'mode' => $record === null ? 'create' : 'edit',
            'submit' => [
                'method' => $record === null ? 'post' : 'put',
                'url' => $record === null
                    ? route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.store',
                    )
                    : route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.update',
                        ['record' => $record->getKey()],
                    ),
            ],
            'cancel' => [
                'url' => $cancelUrl,
            ],
        ]);

        return $this->extensionRegistry->extendPayload($resourceClass, $record === null ? 'create' : 'edit', $payload);
    }
}
