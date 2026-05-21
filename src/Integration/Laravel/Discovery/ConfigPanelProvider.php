<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelHookContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Pages\DashboardPage;

#[Singleton]
final class ConfigPanelProvider implements PanelProviderContract
{
    use ResolvesConfiguredDiscovery;

    /**
     * @var list<class-string<PageDefinitionContract>>
     */
    public const array DEFAULT_PAGE_CLASSES = [
        DashboardPage::class,
    ];

    public function __construct(
        private readonly PanelDefinitionContract $panel,
        private readonly AutoDiscoveryScanner $autoDiscoveryScanner,
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
        $discovered = $this->discoveredClassesFromConfig($config);

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
        $discovered = $this->discoveredClassesFromConfig($config);

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

    protected function autoDiscoveryScanner(): AutoDiscoveryScanner
    {
        return $this->autoDiscoveryScanner;
    }
}
