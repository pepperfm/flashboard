<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

trait ResolvesConfiguredDiscovery
{
    /**
     * @var array{resources: list<class-string<Resource>>, pages: list<class-string<PageDefinitionContract>>}|null
     */
    private ?array $discoveredClassesCache = null;

    abstract protected function autoDiscoveryScanner(): AutoDiscoveryScanner;

    /**
     * @param array<int, mixed> $classes
     *
     * @return list<class-string>
     */
    private function normalizeClasses(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            static fn(mixed $class): bool => is_string($class) && $class !== '',
        ));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{resources: list<class-string<Resource>>, pages: list<class-string<PageDefinitionContract>>}
     */
    private function discoveredClassesFromConfig(array $config): array
    {
        if ($this->discoveredClassesCache !== null) {
            return $this->discoveredClassesCache;
        }
        if (!Arr::get($config, 'discovery.auto.enabled', true)) {
            $this->discoveredClassesCache = [
                'resources' => [],
                'pages' => [],
            ];

            return $this->discoveredClassesCache;
        }

        $targets = array_values(array_map(
            static fn(array $target): DiscoveryTarget => DiscoveryTarget::fromArray($target),
            array_filter(
                (array) Arr::get($config, 'discovery.auto.targets', []),
                static fn(mixed $target): bool => is_array($target),
            ),
        ));

        $this->discoveredClassesCache = $this->autoDiscoveryScanner()->scan(
            $targets,
            $this->normalizeClasses((array) Arr::get($config, 'discovery.auto.except', [])),
        );

        return $this->discoveredClassesCache;
    }
}
