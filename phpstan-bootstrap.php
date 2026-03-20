<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

if (! function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $container = \Illuminate\Container\Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract);
    }
}

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        try {
            $repository = app('config');
        } catch (\Throwable) {
            return $default;
        }

        if ($key === null) {
            return $repository;
        }

        return $repository->get($key, $default);
    }
}

if (! function_exists('auth')) {
    function auth(?string $guard = null): mixed
    {
        return app('auth');
    }
}

if (! function_exists('response')) {
    function response(): mixed
    {
        return app('response');
    }
}

if (! function_exists('redirect')) {
    function redirect(): mixed
    {
        return app('redirect');
    }
}

if (! function_exists('session')) {
    function session(): mixed
    {
        return app('session');
    }
}

if (! function_exists('logger')) {
    function logger(): mixed
    {
        return app('log');
    }
}

if (! function_exists('url')) {
    function url(): mixed
    {
        return app('url');
    }
}

if (! function_exists('abort')) {
    function abort(int $code): never
    {
        throw new \RuntimeException('Abort '.$code);
    }
}

if (! function_exists('abort_unless')) {
    function abort_unless(bool $condition, int $code): void
    {
        if (! $condition) {
            abort($code);
        }
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return __DIR__.'/config'.($path === '' ? '' : '/'.$path);
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return __DIR__.'/resources'.($path === '' ? '' : '/'.$path);
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return __DIR__.'/app'.($path === '' ? '' : '/'.$path);
    }
}

if (! function_exists('back')) {
    function back(
        int $status = 302,
        array $headers = [],
        bool|string $fallback = false,
    ): mixed {
        return redirect()->back($status, $headers, $fallback);
    }
}

if (! function_exists('to_route')) {
    function to_route(
        string $route,
        mixed $parameters = [],
        int $status = 302,
        array $headers = [],
    ): mixed {
        return redirect()->route($route, $parameters, $status, $headers);
    }
}
