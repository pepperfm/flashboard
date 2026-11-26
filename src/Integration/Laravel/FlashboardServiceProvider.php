<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel;

use Pepperfm\Flashboard\Ai\Mcp\FlashboardMcpServer;
use Pepperfm\Flashboard\Contracts\Authorization\ResourcePolicyGateContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Panel\PanelConfig;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\Console\BuildAssetsCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\DoctorCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\InstallCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\PlaygroundInfoCommand;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelDiscovery;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelConfigurationResolver;
use Pepperfm\Flashboard\Integration\Laravel\Http\Middleware\AuthenticatePanelUser;
use Pepperfm\Flashboard\UI\Assets\PublishedAssetManager;
use Laravel\Mcp\Facades\Mcp;

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
        $this->app->singleton(ResourcePolicyGateContract::class, PolicyBridge::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildAssetsCommand::class,
                DoctorCommand::class,
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

        // Opt-in local stdio only: do not expose MCP over HTTP.
        if (
            class_exists(Mcp::class)
            && $this->app->runningInConsole()
            && config('flashboard.ai.mcp_enabled', false)
        ) {
            // The dependency is installed with Flashboard; the local server remains opt-in.
            call_user_func([Mcp::class, 'local'], 'flashboard', FlashboardMcpServer::class);
        }

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
