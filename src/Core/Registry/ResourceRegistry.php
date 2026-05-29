<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Registry;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

#[Singleton]
final class ResourceRegistry
{
    /**
     * @var array<class-string<Resource>, class-string<Resource>>
     */
    private array $resources = [];

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function register(string $resourceClass): void
    {
        if (!is_a($resourceClass, Resource::class, true)) {
            throw new \InvalidArgumentException("Resource [$resourceClass] must extend " . Resource::class . '.');
        }

        $this->resources[$resourceClass] = $resourceClass;
    }

    /**
     * @param list<class-string<Resource>> $resourceClasses
     */
    public function registerMany(array $resourceClasses): void
    {
        foreach ($resourceClasses as $resourceClass) {
            $this->register($resourceClass);
        }
    }

    /**
     * @return list<class-string<Resource>>
     */
    public function all(): array
    {
        return array_values($this->resources);
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function has(string $resourceClass): bool
    {
        return isset($this->resources[$resourceClass]);
    }

    /**
     * @return class-string<Resource>|null
     */
    public function forKey(string $key): ?string
    {
        $key = trim($key);

        return array_find($this->resources, fn($resourceClass) => $resourceClass::key() === $key);
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     *
     * @return class-string<Resource>|null
     */
    public function forModel(string $modelClass): ?string
    {
        $matches = $this->resourcesForModel($modelClass);

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     *
     * @return list<class-string<Resource>>
     */
    public function resourcesForModel(string $modelClass): array
    {
        return array_values(array_filter(
            $this->resources,
            static fn(string $resourceClass): bool => $resourceClass::model() === $modelClass,
        ));
    }
}
