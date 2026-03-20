<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\PanelRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;

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
        return array_values(array_filter(
            (array) Arr::get(config('flashboard', []), 'discovery.providers', []),
            static fn(mixed $provider): bool => is_string($provider) && $provider !== '',
        ));
    }

    private function registerProvider(PanelProviderContract $provider): void
    {
        $this->panelRegistry->register($provider);
        $this->resourceRegistry->registerMany($provider->resources());
        $this->pageRegistry->registerMany($provider->pages());
    }
}
