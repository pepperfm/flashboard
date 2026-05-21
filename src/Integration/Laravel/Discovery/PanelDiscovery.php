<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\PanelRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;

#[Singleton]
final readonly class PanelDiscovery
{
    public function __construct(
        private \Illuminate\Contracts\Foundation\Application $app,
        private ConfigPanelProvider $configPanelProvider,
        private PanelRegistry $panelRegistry,
        private ResourceRegistry $resourceRegistry,
        private PageRegistry $pageRegistry,
    ) {
    }

    public function discover(): void
    {
        $this->registerProvider($this->configPanelProvider);

        foreach ($this->loadedPanelProviders() as $provider) {
            $this->registerProvider($provider);
        }

        foreach ($this->configuredProviderClasses() as $providerClass) {
            $provider = $this->app->make($providerClass);
            if (!$provider instanceof PanelProviderContract) {
                throw new \InvalidArgumentException("Configured panel provider [$providerClass] must implement " . PanelProviderContract::class . '.');
            }

            $this->registerProvider($provider);
        }
    }

    /**
     * @return list<class-string<PanelProviderContract>>
     */
    private function configuredProviderClasses(): array
    {
        $config = Flashboard::resolvedConfig((array) config('flashboard', []));

        return array_values(array_filter(
            (array) Arr::get($config, 'discovery.providers', []),
            static fn(mixed $provider): bool => is_string($provider) && $provider !== '',
        ));
    }

    /**
     * @return list<FlashboardPanelProvider>
     */
    private function loadedPanelProviders(): array
    {
        return array_values(array_filter(
            $this->app->getProviders(FlashboardPanelProvider::class),
            static fn (mixed $provider): bool => $provider instanceof FlashboardPanelProvider,
        ));
    }

    private function registerProvider(PanelProviderContract $provider): void
    {
        $this->panelRegistry->register($provider);
        $this->resourceRegistry->registerMany($provider->resources());
        $this->pageRegistry->registerMany($provider->pages());
    }
}
