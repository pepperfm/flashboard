<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Persistence;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadata;
use Pepperfm\Flashboard\Integration\Laravel\Relations\RelationOptionAccess;

final readonly class RelationSelectionValidator
{
    public function __construct(private RelationOptionAccess $options)
    {
    }

    public function validateSingle(
        BelongsToRelationMetadata $metadata,
        mixed $value,
        ?\Closure $modifier,
        ?Authenticatable $user,
    ): void {
        if ($value === null) {
            return;
        }
        if (!is_string($value) && !is_int($value) && !is_bool($value)) {
            throw $this->invalid($metadata->fieldKey);
        }

        $this->authorizedIds($metadata, [$value], $modifier, $user);
    }

    /**
     * @param list<string|int|bool> $values
     * @return list<string|int|bool>
     */
    public function authorizedIds(
        BelongsToRelationMetadata|BelongsToManyRelationMetadata $metadata,
        array $values,
        ?\Closure $modifier,
        ?Authenticatable $user,
    ): array {
        if ($values === []) {
            return [];
        }
        if (!$this->options->canQuery($metadata, $user)) {
            throw $this->invalid($metadata->fieldKey);
        }

        $key = $metadata instanceof BelongsToRelationMetadata
            ? $metadata->ownerKey
            : $metadata->relatedKey;

        try {
            $query = $this->options->query($metadata, $modifier);
            $records = $query->whereIn($metadata->relatedTable . '.' . $key, $values)->get();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '22P02') {
                throw $this->invalid($metadata->fieldKey);
            }

            throw $exception;
        }

        $allowed = [];

        foreach ($records as $record) {
            if (!$record instanceof Model || !$this->options->canView($metadata, $record, $user)) {
                continue;
            }

            $value = $record->getAttribute($key);

            if (is_string($value) || is_int($value) || is_bool($value)) {
                $allowed[(string) $value] = $value;
            }
        }

        $result = [];

        foreach ($values as $value) {
            if (!array_key_exists((string) $value, $allowed)) {
                throw $this->invalid($metadata->fieldKey);
            }

            $result[] = $allowed[(string) $value];
        }

        return $result;
    }

    private function invalid(string $field): ValidationException
    {
        return ValidationException::withMessages([
            $field => ['The selected records are invalid.'],
        ]);
    }
}
