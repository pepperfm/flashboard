<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelHookContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Pages\DashboardPage;

final class ConfigPanelProvider implements PanelProviderContract
{
    /**
     * @var list<class-string<PageDefinitionContract>>
     */
    public const array DEFAULT_PAGE_CLASSES = [
        DashboardPage::class,
    ];

    /**
     * @var array{resources: list<class-string<Resource>>, pages: list<class-string<PageDefinitionContract>>}|null
     */
    private ?array $discoveredClassesCache = null;

    public function __construct(
        private PanelDefinitionContract $panel,
        private AutoDiscoveryScanner $autoDiscoveryScanner,
    ) {
    }

    public function panel(): PanelDefinitionContract
    {
        return $this->panel;
    }

    /**
     * @return list<class-string<Resource>>
     */
    public function resources(): array
    {
        $config = Flashboard::resolvedConfig((array) config('flashboard', []));
        $discovered = $this->discoveredClasses($config);

        return array_values(array_unique(array_merge(
            $this->normalizeClasses((array) Arr::get($config, 'discovery.resources', [])),
            $discovered['resources'],
        )));
    }

    /**
     * @return list<class-string<PageDefinitionContract>>
     */
    public function pages(): array
    {
        $config = Flashboard::resolvedConfig((array) config('flashboard', []));
        $discovered = $this->discoveredClasses($config);

        return array_values(array_unique(array_merge(
            self::DEFAULT_PAGE_CLASSES,
            $this->normalizeClasses((array) Arr::get($config, 'discovery.pages', [])),
            $discovered['pages'],
        )));
    }

    /**
     * @return list<PanelHookContract>
     */
    public function hooks(): array
    {
        return [];
    }

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
    private function discoveredClasses(array $config): array
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

        $this->discoveredClassesCache = $this->autoDiscoveryScanner->scan(
            $targets,
            $this->normalizeClasses((array) Arr::get($config, 'discovery.auto.except', [])),
        );

        return $this->discoveredClassesCache;
    }
}
