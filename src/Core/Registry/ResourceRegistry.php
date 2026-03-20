<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Registry;

use Pepperfm\Flashboard\Contracts\Resources\Resource;

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
        if (! is_a($resourceClass, Resource::class, true)) {
            throw new \InvalidArgumentException("Resource [$resourceClass] must extend ".Resource::class.'.');
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
}
