<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Resources\Relations\RelationDefinitionContract;

#[Singleton]
final class RelationPayloadFactory
{
    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<array<string, mixed>>
     */
    public function make(string $resourceClass, ?Model $record = null): array
    {
        return array_values(array_map(
            fn(RelationDefinitionContract $relation): array => array_merge(
                $relation->toArray(),
                ['records' => $this->relationRecords($relation, $record)],
            ),
            $resourceClass::relations(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relationRecords(RelationDefinitionContract $relation, ?Model $record): array
    {
        if ($record === null) {
            return [];
        }

        $definition = $relation->toArray();
        $key = (string) ($definition['key'] ?? '');
        if ($key === '' || !method_exists($record, $key)) {
            return [];
        }

        $related = $record->{$key}()->limit(10)->get();
        $titleAttribute = (string) ($definition['title_attribute'] ?? 'name');
        $recordKeyName = (string) ($definition['record_key_name'] ?? 'id');

        return array_values(array_map(
            static fn(Model $item): array => [
                'key' => $item->getAttribute($recordKeyName),
                'title' => $item->getAttribute($titleAttribute),
            ],
            $related->all(),
        ));
    }
}
