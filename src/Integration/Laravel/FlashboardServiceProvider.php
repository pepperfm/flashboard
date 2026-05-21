<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Panel\PanelConfig;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Integration\Laravel\Console\BuildAssetsCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\InstallCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\PlaygroundInfoCommand;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelDiscovery;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelConfigurationResolver;
use Pepperfm\Flashboard\Integration\Laravel\Http\Middleware\AuthenticatePanelUser;
use Pepperfm\Flashboard\UI\Assets\PublishedAssetManager;

final class FlashboardServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), Flashboard::CONFIG_NAME);

        $this->app->singleton(
            PanelDefinitionContract::class,
            function (\Illuminate\Contracts\Foundation\Application $app): PanelDefinitionContract {
                return PanelConfig::fromArray($app->make(PanelConfigurationResolver::class)->resolve());
            }
        );
        $this->app->singleton(
            PublishedAssetManager::class,
            fn (\Illuminate\Contracts\Foundation\Application $app): PublishedAssetManager => new PublishedAssetManager(
                $app->make(\Illuminate\Routing\UrlGenerator::class),
                (string) $app->make('path.public'),
                dirname(__DIR__, 3),
            )
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildAssetsCommand::class,
                InstallCommand::class,
                \Pepperfm\Flashboard\Integration\Laravel\Console\MakePageCommand::class,
                \Pepperfm\Flashboard\Integration\Laravel\Console\MakeProviderCommand::class,
                \Pepperfm\Flashboard\Integration\Laravel\Console\MakeResourceCommand::class,
                PlaygroundInfoCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->app['config']->set(
            Flashboard::CONFIG_NAME,
            $this->app->make(PanelConfigurationResolver::class)->resolve(),
        );

        $this->app->make(PanelDiscovery::class)->discover();
        $this->app['router']->aliasMiddleware('flashboard.auth', AuthenticatePanelUser::class);

        $this->loadRoutesFrom($this->routesPath());
        $this->loadViewsFrom($this->viewsPath(), 'flashboard');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->viewsPath() => resource_path('views/vendor/flashboard'),
            ], 'flashboard-views');

            $this->publishes([
                dirname(__DIR__, 3) . '/public/build' => $this->app->make('path.public') . '/vendor/flashboard/build',
            ], 'flashboard-assets');
        }

        if (config('flashboard.logging.report_boot', false)) {
            $config = $this->app->make(PanelConfigurationResolver::class)->resolve();
        }
    }

    private function configPath(): string
    {
        return dirname(__DIR__, 3) . '/config/flashboard.php';
    }

    private function routesPath(): string
    {
        return dirname(__DIR__, 3) . '/routes/flashboard.php';
    }

    private function viewsPath(): string
    {
        return dirname(__DIR__, 3) . '/resources/views';
    }
}
