<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Inspection;

use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

final readonly class RegisteredResourceLocator
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    /**
     * @return class-string<Resource>
     */
    public function resolve(string $identifier): string
    {
        $identifier = trim($identifier);

        foreach ($this->registry->all() as $resource) {
            if ($resource === $identifier) {
                return $resource;
            }
        }

        $matches = array_values(array_filter(
            $this->registry->all(),
            static fn(string $resource): bool => $resource::key() === $identifier,
        ));
        if (count($matches) !== 1) {
            throw new \InvalidArgumentException('Use an unambiguous registered resource class or route key.');
        }

        return $matches[0];
    }
}
