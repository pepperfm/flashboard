<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final readonly class RelationCreateContext
{
    /**
     * @param class-string<Resource> $parentResource
     */
    public function __construct(
        public string $parentResource,
        public Model $parentRecord,
        public string $relation,
        public RelationManagerMetadata $metadata,
    ) {
    }
}
