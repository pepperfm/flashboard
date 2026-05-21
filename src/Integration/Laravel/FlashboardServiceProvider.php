<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Navigation\NavigationBuilder;
use Pepperfm\Flashboard\Core\Panel\FlashboardManager;
use Pepperfm\Flashboard\Core\Panel\PanelConfig;
use Pepperfm\Flashboard\Core\Relations\RelationPayloadFactory;
use Pepperfm\Flashboard\Core\Registry\PageRegistry;
use Pepperfm\Flashboard\Core\Registry\PanelRegistry;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutor;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\ActionPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\DetailPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\FormPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\ScreenPayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Assemblers\TablePayloadAssembler;
use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeContextFactory;
use Pepperfm\Flashboard\Core\Runtime\Lifecycle\LifecycleManager;
use Pepperfm\Flashboard\Core\Runtime\Metadata\RuntimeMetadataFactory;
use Pepperfm\Flashboard\Core\Runtime\Resolvers\ScreenResolver;
use Pepperfm\Flashboard\Core\Runtime\Workspaces\WorkspacePayloadAssembler;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PanelAuthenticator;
use Pepperfm\Flashboard\Integration\Laravel\Auth\PolicyBridge;
use Pepperfm\Flashboard\Integration\Laravel\Console\BuildAssetsCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\InstallCommand;
use Pepperfm\Flashboard\Integration\Laravel\Console\PlaygroundInfoCommand;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceDetailDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFilterOptionsDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceFormDataSource;
use Pepperfm\Flashboard\Integration\Laravel\DataSources\ResourceListDataSource;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\ConfigPanelProvider;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\AutoDiscoveryScanner;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelDiscovery;
use Pepperfm\Flashboard\Integration\Laravel\Discovery\PanelConfigurationResolver;
use Pepperfm\Flashboard\Integration\Laravel\Http\Middleware\AuthenticatePanelUser;
use Pepperfm\Flashboard\Integration\Laravel\Persistence\ResourceFormPersister;
use Pepperfm\Flashboard\Integration\Laravel\Routing\PanelRouteRegistrar;
use Pepperfm\Flashboard\UI\Assets\PublishedAssetManager;
use Pepperfm\Flashboard\UI\Layout\PanelLayoutFactory;
use Pepperfm\Flashboard\UI\Notifications\NotificationCenter;
use Pepperfm\Flashboard\UI\Overlays\OverlayFactory;
use Pepperfm\Flashboard\UI\Renderers\InertiaScreenRenderer;
use Pepperfm\Flashboard\UI\Renderers\JsonScreenRenderer;
use Pepperfm\Flashboard\UI\States\ScreenStateFactory;

final class FlashboardServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), Flashboard::CONFIG_NAME);

        $this->app->singleton(PanelDefinitionContract::class,
            function (\Illuminate\Contracts\Foundation\Application $app): PanelDefinitionContract {
                return PanelConfig::fromArray($app->make(PanelConfigurationResolver::class)->resolve());
            });

        $this->app->singleton(FlashboardManager::class,
            function (\Illuminate\Contracts\Foundation\Application $app): FlashboardManager {
                return new FlashboardManager($app->make(PanelDefinitionContract::class));
            });

        $this->app->singleton(PanelRegistry::class);
        $this->app->singleton(ResourceRegistry::class);
        $this->app->singleton(PageRegistry::class);
        $this->app->singleton(AutoDiscoveryScanner::class);
        $this->app->singleton(PanelConfigurationResolver::class);

        $this->app->singleton(ConfigPanelProvider::class,
            function (\Illuminate\Contracts\Foundation\Application $app): ConfigPanelProvider {
                return new ConfigPanelProvider(
                    PanelConfig::fromArray($app->make(PanelConfigurationResolver::class)->resolve()),
                    $app->make(AutoDiscoveryScanner::class),
                );
            });

        $this->app->singleton(PanelDiscovery::class);
        $this->app->singleton(PolicyBridge::class);
        $this->app->singleton(ScreenAccessResolver::class);
        $this->app->singleton(NavigationBuilder::class);
        $this->app->singleton(PanelAuthenticator::class);
        $this->app->singleton(ScreenResolver::class);
        $this->app->singleton(RuntimeMetadataFactory::class);
        $this->app->singleton(LifecycleManager::class);
        $this->app->singleton(RuntimeContextFactory::class);
        $this->app->singleton(ActionPayloadAssembler::class);
        $this->app->singleton(TablePayloadAssembler::class);
        $this->app->singleton(FormPayloadAssembler::class);
        $this->app->singleton(DetailPayloadAssembler::class);
        $this->app->singleton(ActionExecutor::class);
        $this->app->singleton(RelationPayloadFactory::class);
        $this->app->singleton(WorkspacePayloadAssembler::class);
        $this->app->singleton(\Pepperfm\Flashboard\Core\Extensions\ExtensionRegistry::class);
        $this->app->singleton(\Pepperfm\Flashboard\Core\Hooks\RuntimeHookDispatcher::class);
        $this->app->singleton(ResourceListDataSource::class);
        $this->app->singleton(ResourceFilterOptionsDataSource::class);
        $this->app->singleton(ResourceFormDataSource::class);
        $this->app->singleton(ResourceDetailDataSource::class);
        $this->app->singleton(ResourceFormPersister::class);
        $this->app->singleton(ScreenPayloadAssembler::class);
        $this->app->singleton(NotificationCenter::class);
        $this->app->singleton(OverlayFactory::class);
        $this->app->singleton(ScreenStateFactory::class);
        $this->app->singleton(PublishedAssetManager::class,
            fn (\Illuminate\Contracts\Foundation\Application $app): PublishedAssetManager => new PublishedAssetManager(
                $app->make(\Illuminate\Routing\UrlGenerator::class),
                (string) $app->make('path.public'),
                dirname(__DIR__, 3),
            ));
        $this->app->singleton(InertiaScreenRenderer::class);
        $this->app->singleton(JsonScreenRenderer::class);
        $this->app->singleton(PanelLayoutFactory::class);
        $this->app->singleton(PanelRouteRegistrar::class);

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

            logger()->info('Flashboard package booted.', [
                'path' => $config['path'] ?? 'admin',
                'route_name_prefix' => $config['route_name_prefix'] ?? 'flashboard.',
            ]);
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
