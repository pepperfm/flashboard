<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Core\Relations\RelationPayloadFactory;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

final readonly class ResourceDetailDataSource
{
    public function __construct(
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private RelationPayloadFactory $relationPayloadFactory,
        private ExtensionRegistry $extensionRegistry,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, ?Model $record): array
    {
        $detail = $resourceClass::detail(Detail::make());
        $schema = $detail->toArray();
        $entries = [];
        $user = $this->authenticator->user();

        if ($record !== null) {
            foreach ($detail->entrySchema() as $entry) {
                $key = (string) ($entry['key'] ?? $entry['name'] ?? '');
                if ($key === '') {
                    continue;
                }
                if (!$this->screenAccessResolver->canViewField($resourceClass, $key, $user)) {
                    continue;
                }

                $entries[] = array_merge($entry, [
                    'value' => data_get($record, $key),
                ]);
            }
        }

        $relations = array_values(array_filter(
            $this->relationPayloadFactory->make($resourceClass, $record),
            fn(array $relation): bool => $this->screenAccessResolver->canViewRelation(
                $resourceClass,
                (string) ($relation['key'] ?? ''),
                $user,
            ),
        ));

        $payload = array_merge($schema, [
            'entries' => $entries,
            'record' => $record?->attributesToArray(),
            'relations' => $relations,
            'routes' => [
                'edit' => $record === null
                    ? null
                    : route(
                        config('flashboard.route_name_prefix', 'flashboard.')
                        . 'resources.' . $resourceClass::key() . '.edit',
                        ['record' => $record->getKey()],
                    ),
                'index' => route(
                    config('flashboard.route_name_prefix', 'flashboard.')
                    . 'resources.' . $resourceClass::key() . '.index',
                ),
            ],
        ]);

        return $this->extensionRegistry->extendPayload($resourceClass, 'detail', $payload);
    }
}
