<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Forms\FormSchemaNodeKind;
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
        $filteredSchema = $this->filterSchemaNodes(
            $schema->schema(),
            $resourceClass,
            $user,
        );
        $fields = $this->flattenFieldNodes($filteredSchema);

        if ($record !== null) {
            foreach ($fields as $field) {
                $key = (string) $field['key'];
                if ($key === '') {
                    continue;
                }

                $state[$key] = data_get($record, $key);
            }
        }
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
            'schema' => $filteredSchema,
            'sections' => $this->extractRootSections($filteredSchema),
            'tabs' => $this->extractRootTabs($filteredSchema),
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

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function filterSchemaNodes(
        array $schema,
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): array {
        $filtered = [];

        foreach ($schema as $node) {
            $normalizedNode = $this->filterSchemaNode($node, $resourceClass, $user);

            if ($normalizedNode !== null) {
                $filtered[] = $normalizedNode;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>|null
     */
    private function filterSchemaNode(
        array $node,
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user,
    ): ?array {
        $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));

        if ($kind === FormSchemaNodeKind::Field) {
            $key = trim((string) Arr::get($node, 'key', ''));

            if ($key === '') {
                return $node;
            }

            return $this->screenAccessResolver->canViewField($resourceClass, $key, $user)
                ? $node
                : null;
        }

        if ($kind === FormSchemaNodeKind::Tabs) {
            $tabs = $this->filterSchemaNodes(
                (array) Arr::get($node, 'tabs', []),
                $resourceClass,
                $user,
            );

            if ($tabs === []) {
                return null;
            }

            $node['tabs'] = $tabs;

            return $node;
        }

        $schema = $this->filterSchemaNodes(
            (array) Arr::get($node, 'schema', []),
            $resourceClass,
            $user,
        );

        if ($schema === []) {
            return null;
        }

        $node['schema'] = $schema;

        return $node;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function flattenFieldNodes(array $schema): array
    {
        $fields = [];

        foreach ($schema as $node) {
            $kind = FormSchemaNodeKind::from((string) Arr::get($node, 'kind', FormSchemaNodeKind::Field->value));

            if ($kind === FormSchemaNodeKind::Field) {
                $fields[] = $node;
                continue;
            }

            if ($kind === FormSchemaNodeKind::Tabs) {
                $fields = array_merge(
                    $fields,
                    $this->flattenFieldNodes((array) Arr::get($node, 'tabs', [])),
                );

                continue;
            }

            $fields = array_merge(
                $fields,
                $this->flattenFieldNodes((array) Arr::get($node, 'schema', [])),
            );
        }

        return $fields;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function extractRootSections(array $schema): array
    {
        return array_values(array_filter(
            $schema,
            fn (array $node): bool => Arr::get($node, 'kind') === FormSchemaNodeKind::Section->value,
        ));
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return list<array<string, mixed>>
     */
    private function extractRootTabs(array $schema): array
    {
        $tabs = [];

        foreach ($schema as $node) {
            if (Arr::get($node, 'kind') !== FormSchemaNodeKind::Tabs->value) {
                continue;
            }

            foreach ((array) Arr::get($node, 'tabs', []) as $tab) {
                if (is_array($tab)) {
                    $tabs[] = $tab;
                }
            }
        }

        return $tabs;
    }
}
