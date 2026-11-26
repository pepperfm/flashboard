<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\DataSources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

final readonly class RelatedTextPath
{
    public function __construct(
        private ScreenAccessResolver $access,
        private ExtensionRegistry $extensions,
    ) {
    }

    public function isRelationPath(Model $model, string $key): bool
    {
        return str_contains($key, '.') && $model->isRelation(str($key)->before('.')->value());
    }

    public static function alias(string $key): string
    {
        return '__fb_text_' . substr(hash('sha256', $key), 0, 24);
    }

    /**
     * @param class-string<Resource> $resourceClass
     * @param Builder<Model> $parent
     */
    public function query(string $resourceClass, Builder $parent, string $key, ?Authenticatable $user): ?QueryBuilder
    {
        if ($parent->getQuery()->from !== $parent->getModel()->getTable()) {
            return null;
        }

        $segments = explode('.', $key);
        if (
            count($segments) < 2
            || count($segments) > 5
            || !$this->access->canViewField($resourceClass, $key, $user)
            || array_any($segments, static fn (string $segment): bool => preg_match('/^[a-zA-Z_]\w*$/D', $segment) !== 1)
        ) {
            return null;
        }

        return $this->resolve($parent, $segments, $resourceClass, $user);
    }

    /**
     * @param Builder<Model> $parent
     * @param list<string> $segments
     * @param class-string<Resource>|null $resourceClass
     */
    private function resolve(
        Builder $parent,
        array $segments,
        ?string $resourceClass,
        ?Authenticatable $user
    ): ?QueryBuilder {
        $name = array_shift($segments);
        $model = $parent->getModel();

        if (
            !$model->isRelation($name)
            || !$this->visible($model, $name)
            || (
                $resourceClass !== null && (
                    !$this->access->canViewRelation($resourceClass, $name, $user)
                    || !$this->access->canViewField($resourceClass, implode('.', [$name, ...$segments]), $user)
                )
            )
        ) {
            return null;
        }

        $relation = Relation::noConstraints(static fn () => $model->{$name}());
        if (!$relation instanceof BelongsTo && !$relation instanceof HasOne) {
            return null;
        }
        if ($relation instanceof HasOne && $relation->isOneOfMany()) {
            return null;
        }

        $related = $relation->getRelated();
        if ($related->getConnection() !== $model->getConnection()) {
            return null;
        }

        $resources = app(ResourceRegistry::class)->resourcesForModel($related::class);
        if (count($resources) > 1) {
            return null;
        }
        $target = $resources[0] ?? null;
        if ($target !== null && !$this->access->canAccessResource($target, $user)) {
            return null;
        }

        $relationQuery = $relation->getQuery()->toBase();
        $orders = $relationQuery->orders ?? [];
        $membership = $this->membership($relationQuery, $related);
        if ($membership === null) {
            return null;
        }

        $outer = $relation->getRelationExistenceQuery((clone $related)->newModelQuery(), $parent);
        $projected = $outer->getModel();
        $outer->whereIn($projected->getQualifiedKeyName(), $membership);

        if ($target !== null) {
            $scope = $this->membership($this->extensions->extendQuery($target, $target::query())->toBase(), $related);
            if ($scope === null) {
                return null;
            }
            $outer->whereIn($projected->getQualifiedKeyName(), $scope);
        }

        if (count($segments) === 1) {
            $field = $segments[0];
            if (
                !$this->visible($related, $field)
                || ($target !== null && !$this->access->canViewField($target, $field, $user))
                || !array_key_exists($field, app(ColumnMetadata::class)->types($related->getConnection(), $related->getTable()))
            ) {
                return null;
            }
            $outer->select($projected->qualifyColumn($field));
        } else {
            $nested = $this->resolve($outer, $segments, $target, $user);
            if ($nested === null) {
                return null;
            }
            $outer->select([]);
            $outer->selectSub($nested, 'related_value');
        }

        foreach ($orders as $order) {
            $column = $order['column'] ?? null;
            if (!is_string($column) || !in_array($order['direction'] ?? null, ['asc', 'desc'], true)) {
                return null;
            }
            $field = str($column)->afterLast('.')->value();
            if (!array_key_exists($field, app(ColumnMetadata::class)->types($related->getConnection(), $related->getTable()))) {
                return null;
            }
            $outer->orderBy($projected->qualifyColumn($field), $order['direction']);
        }
        $outer->orderBy($projected->getQualifiedKeyName())->limit(1);

        return $outer->toBase();
    }

    private function membership(QueryBuilder $query, Model $model): ?QueryBuilder
    {
        if (
            $query->groups !== null
            || $query->havings !== null
            || $query->aggregate !== null
            || $query->unions !== null
            || $query->limit !== null
            || $query->offset !== null
            || $query->groupLimit !== null
            || $query->lock !== null
            || is_array($query->distinct)
            || $query->from !== $model->getTable()
        ) {
            return null;
        }

        return (clone $query)->reorder()->select($model->getQualifiedKeyName())->distinct();
    }

    private function visible(Model $model, string $field): bool
    {
        return !in_array($field, $model->getHidden(), true)
            && ($model->getVisible() === [] || in_array($field, $model->getVisible(), true));
    }
}
