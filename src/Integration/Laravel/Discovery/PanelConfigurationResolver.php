<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Integration\Laravel\FlashboardPanelProvider;

#[Singleton]
final readonly class PanelConfigurationResolver
{
    public function __construct(
        private \Illuminate\Contracts\Foundation\Application $app,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $config = Flashboard::resolvedConfig((array) $this->app['config']->get(Flashboard::CONFIG_NAME, []));

        foreach ($this->providers() as $provider) {
            $config = $provider->resolvePanelConfig($config);
        }

        return $config;
    }

    /**
     * @return list<FlashboardPanelProvider>
     */
    public function providers(): array
    {
        return array_values(array_filter(
            $this->app->getProviders(FlashboardPanelProvider::class),
            static fn (mixed $provider): bool => $provider instanceof FlashboardPanelProvider,
        ));
    }
}
