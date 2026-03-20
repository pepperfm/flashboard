<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $container = \Illuminate\Container\Container::getInstance();
        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $repository = app('config');
        if ($key === null) {
            return $repository;
        }

        return $repository->get($key, $default);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $parameters = []): string
    {
        return "/$name";
    }
}
