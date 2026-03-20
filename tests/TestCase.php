<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests;

use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\NullLogger;

abstract class TestCase extends BaseTestCase
{
    protected TestApplication $app;

    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();
        $this->app = new TestApplication();
        \Illuminate\Container\Container::setInstance($this->app);
        /** @phpstan-ignore-next-line */
        Facade::setFacadeApplication($this->app);

        $config = new Repository([
            'flashboard' => [
                'path' => 'admin',
                'route_name_prefix' => 'flashboard.',
                'middleware' => [
                    'web' => ['web'],
                    'auth' => ['flashboard.auth'],
                ],
                'auth' => [
                    'login_path' => 'login',
                    'logout_path' => 'logout',
                ],
            ],
        ]);

        $this->app->instance('config', $config);
        $this->app->instance('events', new Dispatcher($this->app));
        $this->app->instance('log', new NullLogger());
        $this->router = new Router($this->app['events'], $this->app);
        $this->app->instance('router', $this->router);
        $this->app->alias('router', Router::class);
    }
}
