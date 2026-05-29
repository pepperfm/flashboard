<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Relations;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

final readonly class BelongsToManyRelationMetadata
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
        public string $relatedTable,
        public string $pivotTable,
        public string $foreignPivotKey,
        public string $relatedPivotKey,
        public string $parentKey,
        public string $relatedKey,
        public string $recordKeyName,
        public string $titleAttribute,
        public array $searchColumns,
        public int $optionsPerPage,
        public ?int $maxItems,
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
            'related_table' => $this->relatedTable,
            'pivot_table' => $this->pivotTable,
            'foreign_pivot_key' => $this->foreignPivotKey,
            'related_pivot_key' => $this->relatedPivotKey,
            'parent_key' => $this->parentKey,
            'related_key' => $this->relatedKey,
            'record_key_name' => $this->recordKeyName,
            'title_attribute' => $this->titleAttribute,
            'search_columns' => $this->searchColumns,
            'options_per_page' => $this->optionsPerPage,
            'max_items' => $this->maxItems,
            'allow_model_fallback' => $this->allowModelFallback,
        ];
    }
}
