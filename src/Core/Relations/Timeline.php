<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Relations;

final class Timeline extends RelationManagerDefinition
{
    private string $dateAttribute = 'created_at';

    private ?string $descriptionAttribute = null;

    public function dateAttribute(string $attribute): static
    {
        $this->dateAttribute = $attribute;

        return $this;
    }

    public function descriptionAttribute(string $attribute): static
    {
        $this->descriptionAttribute = $attribute;

        return $this;
    }

    public function toArray(): array
    {
        return array_replace(parent::toArray(), [
            'presentation' => 'timeline',
            'date_attribute' => $this->dateAttribute,
            'description_attribute' => $this->descriptionAttribute,
            self::ATTRIBUTE_READ_ONLY => true,
            self::ATTRIBUTE_ATTACHABLE => false,
            self::ATTRIBUTE_DETACHABLE => false,
            self::ATTRIBUTE_REPLACEABLE => false,
            self::ATTRIBUTE_SYNCABLE => false,
            self::ATTRIBUTE_SEARCH_COLUMNS => [],
        ]);
    }

    protected function type(): string
    {
        return HasMany::TYPE;
    }
}
