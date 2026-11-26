<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToRelationMetadata;
use Pepperfm\Flashboard\Core\Forms\Relations\BelongsToManyRelationMetadata;

final readonly class RelationOptionAccess
{
    public function __construct(
        private ExtensionRegistry $extensions,
        private ScreenAccessResolver $access,
    ) {
    }

    public function canQuery(
        BelongsToRelationMetadata|BelongsToManyRelationMetadata $metadata,
        ?Authenticatable $user,
    ): bool {
        return $metadata->relatedResource === null
            ? $metadata->allowModelFallback
            : $this->access->canAccessResource($metadata->relatedResource, $user);
    }

    /** @return Builder<Model> */
    public function query(
        BelongsToRelationMetadata|BelongsToManyRelationMetadata $metadata,
        ?\Closure $modifier,
    ): Builder {
        $resource = $metadata->relatedResource;

        if ($resource !== null) {
            $query = $this->extensions->extendQuery($resource, $resource::query());
        } else {
            $model = $metadata->relatedModel;
            $query = $model::query();
        }

        return RelationQueryModifier::apply($modifier, $query, $metadata->fieldKey);
    }

    public function canView(
        BelongsToRelationMetadata|BelongsToManyRelationMetadata $metadata,
        Model $record,
        ?Authenticatable $user,
    ): bool {
        return $metadata->relatedResource === null
            ? $metadata->allowModelFallback
            : $this->access->canViewRecord($metadata->relatedResource, $user, $record);
    }
}
