<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\DetailPayloadAssembler;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Core\Detail\Normalization\JsonEntryValue;
use Pepperfm\Flashboard\Core\Relations\RelationPayloadFactory;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;

#[Singleton]
final readonly class ResourceDetailDataSource
{
    public function __construct(
        private DetailPayloadAssembler $detailPayloadAssembler,
        private ScreenAccessResolver $screenAccessResolver,
        private PanelAuthenticator $authenticator,
        private RelationPayloadFactory $relationPayloadFactory,
        private ResourceRelationRecordsDataSource $relationRecordsDataSource,
        private ExtensionRegistry $extensionRegistry,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, mixed>
     */
    public function resolve(string $resourceClass, ?Model $record, string $relationPlacement = 'detail'): array
    {
        $detail = $resourceClass::detail(Detail::make());
        $schema = $this->detailPayloadAssembler->assemble($resourceClass);
        $entries = [];
        $user = $this->authenticator->user();

        if ($record !== null) {
            $relatedPaths = new RelatedTextPath($this->screenAccessResolver, $this->extensionRegistry);
            foreach ($schema->entries() as $entry) {
                $key = (string) $entry['key'];
                if ($key === '') {
                    continue;
                }
                if (!$this->screenAccessResolver->canViewField($resourceClass, $key, $user)) {
                    continue;
                }
                if (($entry['type'] ?? null) === 'json') {
                    if (!$this->canExposeJsonAttribute($record, $key)) {
                        continue;
                    }

                    $entries[] = array_merge($entry, [
                        'value' => $this->jsonEntryValue($record, $key),
                    ]);

                    continue;
                }

                if (($entry['type'] ?? null) === 'text' && $relatedPaths->isRelationPath($record, $key)) {
                    $query = $record->newModelQuery()->whereKey($record->getKey());
                    $subquery = $relatedPaths->query($resourceClass, $query, $key, $user);
                    if ($subquery === null) {
                        continue;
                    }

                    $query->selectSub($subquery, 'related_value');
                    $entries[] = array_merge($entry, [
                        'value' => $query->toBase()->value('related_value'),
                    ]);

                    continue;
                }

                $entries[] = array_merge($entry, [
                    'value' => data_get($record, $key),
                ]);
            }
        }

        $relations = array_values(array_filter(
            array_merge(
                $this->relationPayloadFactory->make($resourceClass, $record),
                $this->relationRecordsDataSource->initialPayloads($resourceClass, $record, $user, $relationPlacement),
            ),
            fn(array $relation): bool => $this->screenAccessResolver->canViewRelation(
                $resourceClass,
                (string) Arr::get($relation, 'key', ''),
                $user,
            ),
        ));

        $payload = array_merge($schema->toArray(), [
            'entries' => $entries,
            // Never serialize the entire Eloquent record: only authorized detail entries are exposed above.
            'record' => $record === null ? null : [$record->getKeyName() => $record->getKey()],
            'relations' => $relations,
            'routes' => [
                'edit' => $record === null || !$this->screenAccessResolver->canEditRecord($resourceClass, $user, $record)
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

    /**
     * @return array{status: string, text: string|null}
     */
    private function jsonEntryValue(Model $record, string $key): array
    {
        $raw = $record->getRawOriginal($key);
        if (is_string($raw) && strlen($raw) > JsonEntryValue::MAX_INPUT_BYTES) {
            return ['status' => 'truncated', 'text' => null];
        }

        $hasAccessor = $record->hasGetMutator($key) || $record->hasAttributeGetMutator($key);
        if (!$hasAccessor && $record->hasCast($key, ['array', 'json', 'object'])) {
            return JsonEntryValue::present($raw);
        }

        return JsonEntryValue::present(
            $record->getAttribute($key),
            !$hasAccessor,
        );
    }

    private function canExposeJsonAttribute(Model $record, string $key): bool
    {
        return !str_contains($key, '.')
            && $record->hasAttribute($key)
            && !in_array($key, $record->getHidden(), true)
            && ($record->getVisible() === [] || in_array($key, $record->getVisible(), true));
    }
}
