<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Relations;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

final readonly class BelongsToRelationMetadata
{
    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $relatedModel
     * @param class-string<Resource>|null $relatedResource
     * @param list<string> $searchColumns
     */
    public function __construct(
        public string $fieldKey,
        public string $relationship,
        public string $relatedModel,
        public ?string $relatedResource,
        public string $foreignKey,
        public string $ownerKey,
        public string $recordKeyName,
        public string $relatedTable,
        public string $titleAttribute,
        public array $searchColumns,
        public int $optionsPerPage,
        public bool $allowModelFallback,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'relationship' => $this->relationship,
            'related_model' => $this->relatedModel,
            'related_resource' => $this->relatedResource,
            'foreign_key' => $this->foreignKey,
            'owner_key' => $this->ownerKey,
            'record_key_name' => $this->recordKeyName,
            'related_table' => $this->relatedTable,
            'title_attribute' => $this->titleAttribute,
            'search_columns' => $this->searchColumns,
            'options_per_page' => $this->optionsPerPage,
            'allow_model_fallback' => $this->allowModelFallback,
        ];
    }
}
