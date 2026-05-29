<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RelationQueryModifier
{
    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     *
     * @return Builder<TModel>
     */
    public static function apply(?\Closure $modifier, Builder $query, string $context): Builder
    {
        if ($modifier === null) {
            return $query;
        }

        $modifiedQuery = $modifier($query);
        if (!$modifiedQuery instanceof Builder) {
            throw new \UnexpectedValueException(sprintf(
                'Relation query modifier [%s] must return an Eloquent query builder.',
                $context,
            ));
        }

        return $modifiedQuery;
    }
}
