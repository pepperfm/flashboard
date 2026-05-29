<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Forms\Fields;

use Pepperfm\Flashboard\Contracts\Forms\FieldRenderer;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

class BelongsToMany extends Field
{
    public const string ATTRIBUTE_ALLOW_MODEL_FALLBACK = 'allow_model_fallback';
    public const string ATTRIBUTE_MAX_ITEMS = 'max_items';
    public const string ATTRIBUTE_MODEL = 'related_model';
    public const string ATTRIBUTE_OPTIONS_PER_PAGE = 'options_per_page';
    public const string ATTRIBUTE_RECORD_KEY_NAME = 'record_key_name';
    public const string ATTRIBUTE_RELATED_RESOURCE = 'related_resource';
    public const string ATTRIBUTE_RELATIONSHIP = 'relationship';
    public const string ATTRIBUTE_SEARCH_COLUMNS = 'search_columns';
    public const string ATTRIBUTE_TITLE_ATTRIBUTE = 'title_attribute';

    public const int DEFAULT_OPTIONS_PER_PAGE = BelongsTo::DEFAULT_OPTIONS_PER_PAGE;

    private ?\Closure $modifyQueryUsing = null;

    public static function make(string $key, ?string $label = null, ?string $relationship = null): static
    {
        return parent::make($key, $label)
            ->type(self::TYPE_BELONGS_TO_MANY)
            ->relationship($relationship ?? self::inferRelationshipName($key))
            ->optionsPerPage(self::DEFAULT_OPTIONS_PER_PAGE);
    }

    public function relationship(string $name): static
    {
        return $this->attribute(self::ATTRIBUTE_RELATIONSHIP, trim($name));
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        return $this->attribute(self::ATTRIBUTE_RELATED_RESOURCE, $resourceClass);
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    public function model(string $modelClass): static
    {
        return $this
            ->attribute(self::ATTRIBUTE_MODEL, $modelClass)
            ->allowModelFallback();
    }

    public function allowModelFallback(bool $condition = true): static
    {
        return $this->attribute(self::ATTRIBUTE_ALLOW_MODEL_FALLBACK, $condition);
    }

    public function recordKeyName(string $column): static
    {
        return $this->attribute(self::ATTRIBUTE_RECORD_KEY_NAME, $column);
    }

    public function titleAttribute(string $attribute): static
    {
        return $this->attribute(self::ATTRIBUTE_TITLE_ATTRIBUTE, $attribute);
    }

    /**
     * @param list<string>|string|bool $columns
     */
    public function searchable(array|string|bool $columns = true): static
    {
        if ($columns === false) {
            return $this->attribute(self::ATTRIBUTE_SEARCH_COLUMNS, []);
        }
        if ($columns === true) {
            return $this->attribute(self::ATTRIBUTE_SEARCH_COLUMNS, true);
        }

        $columns = is_string($columns) ? [$columns] : $columns;

        return $this->attribute(
            self::ATTRIBUTE_SEARCH_COLUMNS,
            array_values(array_filter(
                array_map(static fn (string $column): string => trim($column), $columns),
                static fn (string $column): bool => $column !== '',
            )),
        );
    }

    public function optionsPerPage(int $count): static
    {
        return $this->attribute(self::ATTRIBUTE_OPTIONS_PER_PAGE, max(1, $count));
    }

    public function maxItems(int $count): static
    {
        return $this->attribute(self::ATTRIBUTE_MAX_ITEMS, max(1, $count));
    }

    /**
     * @param (callable(\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>): \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>)|null $callback
     */
    public function modifyQueryUsing(?callable $callback): static
    {
        $this->modifyQueryUsing = $callback === null ? null : $callback(...);

        return $this;
    }

    public function queryModifier(): ?\Closure
    {
        return $this->modifyQueryUsing;
    }

    protected function defaultRenderer(): ?FieldRenderer
    {
        return FieldRenderer::RelationMultiSelect;
    }

    private static function inferRelationshipName(string $key): string
    {
        return trim($key);
    }
}
