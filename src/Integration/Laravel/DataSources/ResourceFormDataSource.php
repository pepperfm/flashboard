<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class ResourceFormDataSource
{
    public function __construct(
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private ExtensionRegistry $extensionRegistry,
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
        $schema = $form->toArray();
        $state = $form->defaultState();
        $user = $this->authenticator->user();

        if ($record !== null) {
            foreach ($form->fieldSchema() as $field) {
                $key = (string) Arr::get($field, 'key', Arr::get($field, 'name', ''));
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
            $form->fieldSchema(),
            fn(array $field): bool => $this->screenAccessResolver->canViewField(
                $resourceClass,
                (string) Arr::get($field, 'key', Arr::get($field, 'name', '')),
                $user,
            ),
        ));

        $payload = array_merge($schema, [
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
                'url' => $record === null
                    ? route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.index',
                    )
                    : route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.detail',
                        ['record' => $record->getKey()],
                    ),
            ],
        ]);

        return $this->extensionRegistry->extendPayload($resourceClass, $record === null ? 'create' : 'edit', $payload);
    }
}
