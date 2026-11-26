<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;

final readonly class TimelinePresentation
{
    public function __construct(
        public string $dateAttribute,
        public ?string $descriptionAttribute,
    ) {
    }

    /** @param array<string, mixed> $definition
     * @param array<string, string> $columns
     */
    public static function fromDefinition(array $definition, array $columns): self
    {
        $date = $definition['date_attribute'] ?? 'created_at';
        $description = $definition['description_attribute'] ?? null;

        if (!is_string($date) || !in_array(strtolower($columns[$date] ?? ''), [
            'date', 'datetime', 'timestamp', 'timestamp without time zone', 'timestamp with time zone', 'timestamptz',
        ], true)) {
            throw new \InvalidArgumentException('Timeline requires a physical date or timestamp column.');
        }
        if ($description !== null && (!is_string($description) || !array_key_exists($description, $columns))) {
            throw new \InvalidArgumentException('Timeline description must be a physical model column.');
        }

        return new self($date, $description);
    }

    public function canExpose(
        RelationManagerMetadata $metadata,
        Model $record,
        string $field,
        ?Authenticatable $user,
        ScreenAccessResolver $access,
    ): bool {
        return !in_array($field, $record->getHidden(), true)
            && ($record->getVisible() === [] || in_array($field, $record->getVisible(), true))
            && ($metadata->relatedResource === null || $access->canViewField($metadata->relatedResource, $field, $user));
    }

    public function text(Model $record, string $field, int $maxBytes): ?string
    {
        $value = $record->getAttribute($field);
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:sP');
        }

        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $text = (string) $value;

        return strlen($text) > $maxBytes ? mb_strcut($text, 0, $maxBytes, 'UTF-8').'…' : $text;
    }
}
