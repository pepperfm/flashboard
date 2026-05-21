<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel;

use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelHookContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Pages\DashboardPage;
use Pepperfm\Flashboard\Core\Panel\PanelConfig;
use Pepperfm\Flashboard\FlashboardConfig;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\AutoDiscoveryScanner;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\ResolvesConfiguredDiscovery;

abstract class FlashboardPanelProvider extends \Illuminate\Support\ServiceProvider implements PanelProviderContract
{
    use ResolvesConfiguredDiscovery;

    private FlashboardConfig $flashboardConfig;

    public function __construct($app)
    {
        parent::__construct($app);

        $this->flashboardConfig = new FlashboardConfig();
    }

    public function panel(): PanelDefinitionContract
    {
        return PanelConfig::fromArray($this->resolvedConfiguration());
    }

    /**
     * @return list<class-string<Resource>>
     */
    public function resources(): array
    {
        $config = $this->resolvedConfiguration();
        $discovered = $this->discoveredClassesFromConfig($config);

        return array_values(array_unique(array_merge(
            $this->normalizeClasses((array) ($config['discovery']['resources'] ?? [])),
            $discovered['resources'],
        )));
    }

    /**
     * @return list<class-string<PageDefinitionContract>>
     */
    public function pages(): array
    {
        $config = $this->resolvedConfiguration();
        $discovered = $this->discoveredClassesFromConfig($config);

        return array_values(array_unique(array_merge(
            [DashboardPage::class],
            $this->normalizeClasses((array) ($config['discovery']['pages'] ?? [])),
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

    protected function panelConfig(): FlashboardConfig
    {
        return $this->flashboardConfig;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function resolvePanelConfig(array $config = []): array
    {
        return $this->panelConfig()->merge($config);
    }

    protected function autoDiscoveryScanner(): AutoDiscoveryScanner
    {
        /** @var AutoDiscoveryScanner $scanner */
        $scanner = $this->app->make(AutoDiscoveryScanner::class);

        return $scanner;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedConfiguration(): array
    {
        return $this->resolvePanelConfig();
    }
}
