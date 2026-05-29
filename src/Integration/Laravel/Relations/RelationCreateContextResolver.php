<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Relations;

use Illuminate\Database\Eloquent\Model;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Relations\HasMany;
use Pepperfm\Flashboard\Core\Relations\HasOne;
use Pepperfm\Flashboard\Core\Relations\RelationManagerDefinition;

final readonly class RelationCreateContextResolver
{
    public function __construct(
        private ResourceRegistry $resourceRegistry,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function resolve(\Illuminate\Http\Request $request, string $resourceClass): ?RelationCreateContext
    {
        $parentResourceKey = trim((string) $request->query('parent_resource', $request->input('parent_resource', '')));
        $parentRecordKey = $request->query('parent_record', $request->input('parent_record'));
        $relationKey = trim((string) $request->query('relation', $request->input('relation', '')));

        if ($parentResourceKey === '' && $parentRecordKey === null && $relationKey === '') {
            return null;
        }
        if ($parentResourceKey === '' || $parentRecordKey === null || $relationKey === '') {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $parentResource = $this->resourceRegistry->forKey($parentResourceKey);
        if ($parentResource === null) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $parentRecord = $parentResource::resolveRecord($parentRecordKey);
        if (!$parentRecord instanceof Model) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        foreach ($parentResource::relations() as $relation) {
            $definition = $relation->toArray();
            $type = (string) ($definition[RelationManagerDefinition::ATTRIBUTE_TYPE] ?? '');

            if (
                (string) ($definition['key'] ?? '') !== $relationKey
                || !in_array($type, [HasOne::TYPE, HasMany::TYPE], true)
            ) {
                continue;
            }

            $metadata = new RelationManagerMetadataResolver($this->resourceRegistry)->resolve($parentResource, $definition);
            if ($metadata->relatedResource !== $resourceClass) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }

            return new RelationCreateContext($parentResource, $parentRecord, $relationKey, $metadata);
        }

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
}
