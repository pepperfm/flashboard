<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Panel;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;

final readonly class PanelConfig implements PanelDefinitionContract
{
    /**
     * @param list<string> $webMiddleware
     * @param list<string> $authMiddleware
     */
    public function __construct(
        private string $name,
        private string $path,
        private string $routeNamePrefix,
        private ?string $guard,
        private array $webMiddleware,
        private array $authMiddleware,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $path = trim((string) Arr::get($config, 'path', 'admin'), '/');
        $routeNamePrefix = (string) Arr::get($config, 'route_name_prefix', 'flashboard.');
        $guard = Arr::get($config, 'guard');
        $webMiddleware = self::normalizeMiddleware((array) Arr::get($config, 'middleware.web', ['web']));
        $authMiddleware = self::normalizeMiddleware((array) Arr::get($config, 'middleware.auth', ['auth']));

        if ($path === '') {
            $path = 'admin';
        }
        if ($routeNamePrefix === '') {
            $routeNamePrefix = 'flashboard.';
        } elseif (!str_ends_with($routeNamePrefix, '.')) {
            $routeNamePrefix .= '.';
        }
        if (is_string($guard) && $guard !== '' && !self::containsAuthMiddleware($authMiddleware)) {
            $authMiddleware[] = 'auth:' . $guard;
        }

        return new self(
            name: (string) Arr::get($config, 'name', 'Flashboard'),
            path: $path,
            routeNamePrefix: $routeNamePrefix,
            guard: is_string($guard) && $guard !== '' ? $guard : null,
            webMiddleware: $webMiddleware === [] ? ['web'] : $webMiddleware,
            authMiddleware: $authMiddleware === [] ? ['auth'] : $authMiddleware,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function routeNamePrefix(): string
    {
        return $this->routeNamePrefix;
    }

    public function guard(): ?string
    {
        return $this->guard;
    }

    /**
     * @return list<string>
     */
    public function webMiddleware(): array
    {
        return $this->webMiddleware;
    }

    /**
     * @return list<string>
     */
    public function authMiddleware(): array
    {
        return $this->authMiddleware;
    }

    /**
     * @return list<string>
     */
    private static function normalizeMiddleware(array $middleware): array
    {
        return array_values(array_filter(
            $middleware,
            static fn(mixed $value): bool => is_string($value) && $value !== '',
        ));
    }

    /**
     * @param list<string> $middleware
     */
    private static function containsAuthMiddleware(array $middleware): bool
    {
        return array_any($middleware, fn($value) => $value === 'auth' || str_starts_with($value, 'auth:'));
    }
}
