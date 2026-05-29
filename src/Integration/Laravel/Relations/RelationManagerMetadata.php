<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

final readonly class RelationManagerMetadata
{
    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $parentModel
     * @param class-string<\Illuminate\Database\Eloquent\Model> $relatedModel
     * @param class-string<Resource>|null $relatedResource
     * @param list<string> $searchColumns
     * @param array<string, mixed> $definition
     */
    public function __construct(
        public string $type,
        public string $key,
        public string $label,
        public string $relationship,
        public string $parentModel,
        public string $relatedModel,
        public ?string $relatedResource,
        public string $relatedTable,
        public string $localKey,
        public string $foreignKey,
        public string $recordKeyName,
        public string $titleAttribute,
        public array $searchColumns,
        public int $perPage,
        public bool $readOnly,
        public bool $attachable,
        public bool $detachable,
        public bool $replaceable,
        public bool $syncable,
        public bool $showOnDetail,
        public bool $showOnEdit,
        public array $definition,
    ) {
    }
}
