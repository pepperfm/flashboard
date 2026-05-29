<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations;

use Pepperfm\Flashboard\Contracts\Resources\Relations\RelationDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Relations\Concerns\InfersRelationshipName;

abstract class RelationManagerDefinition implements RelationDefinitionContract
{
    use InfersRelationshipName;

    public const string ATTRIBUTE_ATTACHABLE = 'attachable';
    public const string ATTRIBUTE_DETACHABLE = 'detachable';
    public const string ATTRIBUTE_FOREIGN_KEY = 'foreign_key';
    public const string ATTRIBUTE_LOCAL_KEY = 'local_key';
    public const string ATTRIBUTE_MODEL = 'related_model';
    public const string ATTRIBUTE_PER_PAGE = 'per_page';
    public const string ATTRIBUTE_READ_ONLY = 'read_only';
    public const string ATTRIBUTE_RECORD_KEY_NAME = 'record_key_name';
    public const string ATTRIBUTE_RELATED_RESOURCE = 'related_resource';
    public const string ATTRIBUTE_RELATIONSHIP = 'relationship';
    public const string ATTRIBUTE_REPLACEABLE = 'replaceable';
    public const string ATTRIBUTE_SEARCH_COLUMNS = 'search_columns';
    public const string ATTRIBUTE_SHOW_ON_DETAIL = 'show_on_detail';
    public const string ATTRIBUTE_SHOW_ON_EDIT = 'show_on_edit';
    public const string ATTRIBUTE_SYNCABLE = 'syncable';
    public const string ATTRIBUTE_TITLE_ATTRIBUTE = 'title_attribute';
    public const string ATTRIBUTE_TYPE = 'type';
    public const string ATTRIBUTE_VISIBLE = 'visible';

    public const int DEFAULT_PER_PAGE = 10;

    private ?string $model = null;

    private ?string $relatedResource = null;

    private ?string $localKey = null;

    private ?string $foreignKey = null;

    private string $recordKeyName = 'id';

    private string $titleAttribute = 'name';

    /**
     * @var list<string>|bool
     */
    private array|bool $searchColumns = true;

    private int $perPage = self::DEFAULT_PER_PAGE;

    private bool $readOnly = false;

    private bool $attachable = false;

    private bool $detachable = false;

    private bool $replaceable = false;

    private bool $syncable = false;

    private bool $showOnDetail = true;

    private bool $showOnEdit = false;

    private bool $visible = true;

    final protected function __construct(
        private readonly string $key,
        private ?string $label = null,
        private ?string $relationship = null,
    ) {
        $this->label ??= str($this->key)->headline()->value();
        $this->relationship ??= self::inferRelationshipName($this->key);
    }

    public static function make(string $key, ?string $label = null, ?string $relationship = null): static
    {
        return new static($key, $label, $relationship);
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function relationship(string $name): static
    {
        $this->relationship = trim($name);

        return $this;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function resource(string $resourceClass): static
    {
        $this->relatedResource = $resourceClass;

        return $this;
    }

    public function localKey(string $key): static
    {
        $this->localKey = $key;

        return $this;
    }

    public function foreignKey(string $key): static
    {
        $this->foreignKey = $key;

        return $this;
    }

    public function titleAttribute(string $attribute): static
    {
        $this->titleAttribute = $attribute;

        return $this;
    }

    public function recordKeyName(string $key): static
    {
        $this->recordKeyName = $key;

        return $this;
    }

    /**
     * @param list<string>|string|bool $columns
     */
    public function searchable(array|string|bool $columns = true): static
    {
        if ($columns === false) {
            $this->searchColumns = [];

            return $this;
        }
        if ($columns === true) {
            $this->searchColumns = true;

            return $this;
        }

        $columns = is_string($columns) ? [$columns] : $columns;
        $this->searchColumns = array_values(array_filter(
            array_map(static fn (string $column): string => trim($column), $columns),
            static fn (string $column): bool => $column !== '',
        ));

        return $this;
    }

    public function perPage(int $count): static
    {
        $this->perPage = max(1, $count);

        return $this;
    }

    public function readOnly(bool $condition = true): static
    {
        $this->readOnly = $condition;

        return $this;
    }

    public function attachable(bool $condition = true): static
    {
        $this->attachable = $condition;

        return $this;
    }

    public function detachable(bool $condition = true): static
    {
        $this->detachable = $condition;

        return $this;
    }

    public function replaceable(bool $condition = true): static
    {
        $this->replaceable = $condition;

        return $this;
    }

    public function syncable(bool $condition = true): static
    {
        $this->syncable = $condition;

        return $this;
    }

    public function showOnDetail(bool $condition = true): static
    {
        $this->showOnDetail = $condition;

        return $this;
    }

    public function showOnEdit(bool $condition = true): static
    {
        $this->showOnEdit = $condition;

        return $this;
    }

    public function visible(bool $condition): static
    {
        $this->visible = $condition;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            self::ATTRIBUTE_TYPE => $this->type(),
            'key' => $this->key,
            'label' => $this->label,
            self::ATTRIBUTE_RELATIONSHIP => $this->relationship,
            self::ATTRIBUTE_RELATED_RESOURCE => $this->relatedResource,
            self::ATTRIBUTE_MODEL => $this->model,
            self::ATTRIBUTE_LOCAL_KEY => $this->localKey,
            self::ATTRIBUTE_FOREIGN_KEY => $this->foreignKey,
            self::ATTRIBUTE_RECORD_KEY_NAME => $this->recordKeyName,
            self::ATTRIBUTE_TITLE_ATTRIBUTE => $this->titleAttribute,
            self::ATTRIBUTE_SEARCH_COLUMNS => $this->searchColumns,
            self::ATTRIBUTE_PER_PAGE => $this->perPage,
            self::ATTRIBUTE_READ_ONLY => $this->readOnly,
            self::ATTRIBUTE_ATTACHABLE => $this->attachable,
            self::ATTRIBUTE_DETACHABLE => $this->detachable,
            self::ATTRIBUTE_REPLACEABLE => $this->replaceable,
            self::ATTRIBUTE_SYNCABLE => $this->syncable,
            self::ATTRIBUTE_SHOW_ON_DETAIL => $this->showOnDetail,
            self::ATTRIBUTE_SHOW_ON_EDIT => $this->showOnEdit,
            self::ATTRIBUTE_VISIBLE => $this->visible,
        ];
    }

    abstract protected function type(): string;
}
