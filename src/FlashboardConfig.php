<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard;

use Pepperfm\Flashboard\Core\Panel\DiscoveryScope;
use Pepperfm\Flashboard\Core\Panel\DiscoveryTarget;

final class FlashboardConfig
{
    private ?string $name = null;

    private ?string $path = null;

    private ?string $routeNamePrefix = null;

    private ?string $guard = null;

    /**
     * @var list<string>|null
     */
    private ?array $webMiddleware = null;

    /**
     * @var list<string>|null
     */
    private ?array $authMiddleware = null;

    private ?string $loginPath = null;

    private ?string $logoutPath = null;

    private ?string $usernameField = null;

    private ?string $passwordField = null;

    private ?string $rememberKey = null;

    /**
     * @var list<class-string>
     */
    private array $providerClasses = [];

    /**
     * @var list<class-string>
     */
    private array $resourceClasses = [];

    /**
     * @var list<class-string>
     */
    private array $pageClasses = [];

    /**
     * @var list<DiscoveryTarget>
     */
    private array $discoveryTargets = [];

    /**
     * @var list<string>
     */
    private array $excludedDiscoveryClasses = [];

    private bool $autoDiscoveryEnabled = true;

    private ?bool $reportBoot = null;

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function path(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function routeNamePrefix(string $routeNamePrefix): static
    {
        $this->routeNamePrefix = $routeNamePrefix;

        return $this;
    }

    public function guard(?string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function webMiddleware(string ...$middleware): static
    {
        $this->webMiddleware = self::normalizeValues($middleware);

        return $this;
    }

    public function authMiddleware(string ...$middleware): static
    {
        $this->authMiddleware = self::normalizeValues($middleware);

        return $this;
    }

    public function loginPath(string $loginPath): static
    {
        $this->loginPath = $loginPath;

        return $this;
    }

    public function logoutPath(string $logoutPath): static
    {
        $this->logoutPath = $logoutPath;

        return $this;
    }

    public function usernameField(string $usernameField): static
    {
        $this->usernameField = $usernameField;

        return $this;
    }

    public function passwordField(string $passwordField): static
    {
        $this->passwordField = $passwordField;

        return $this;
    }

    public function rememberKey(string $rememberKey): static
    {
        $this->rememberKey = $rememberKey;

        return $this;
    }

    public function provider(string $providerClass): static
    {
        $this->providerClasses[] = $providerClass;
        $this->providerClasses = array_values(array_unique($this->providerClasses));

        return $this;
    }

    /**
     * @param list<class-string> $providerClasses
     */
    public function providers(array $providerClasses): static
    {
        foreach ($providerClasses as $providerClass) {
            $this->provider($providerClass);
        }

        return $this;
    }

    public function resource(string $resourceClass): static
    {
        $this->resourceClasses[] = $resourceClass;
        $this->resourceClasses = array_values(array_unique($this->resourceClasses));

        return $this;
    }

    /**
     * @param list<class-string> $resourceClasses
     */
    public function resources(array $resourceClasses): static
    {
        foreach ($resourceClasses as $resourceClass) {
            $this->resource($resourceClass);
        }

        return $this;
    }

    public function page(string $pageClass): static
    {
        $this->pageClasses[] = $pageClass;
        $this->pageClasses = array_values(array_unique($this->pageClasses));

        return $this;
    }

    public function discover(?string $in = null, ?string $namespace = null): static
    {
        return $this->addDiscoveryTarget($in, $namespace, DiscoveryScope::Both);
    }

    public function discoverResources(?string $in = null, ?string $namespace = null): static
    {
        return $this->addDiscoveryTarget($in, $namespace, DiscoveryScope::Resources);
    }

    public function discoverPages(?string $in = null, ?string $namespace = null): static
    {
        return $this->addDiscoveryTarget($in, $namespace, DiscoveryScope::Pages);
    }

    public function withoutDiscovery(): static
    {
        $this->autoDiscoveryEnabled = false;

        return $this;
    }

    public function except(string ...$classes): static
    {
        $this->excludedDiscoveryClasses = array_values(array_unique(array_merge(
            $this->excludedDiscoveryClasses,
            self::normalizeValues($classes),
        )));

        return $this;
    }

    /**
     * @param list<class-string> $pageClasses
     */
    public function pages(array $pageClasses): static
    {
        foreach ($pageClasses as $pageClass) {
            $this->page($pageClass);
        }

        return $this;
    }

    public function reportBoot(bool $reportBoot = true): static
    {
        $this->reportBoot = $reportBoot;

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function merge(array $config): array
    {
        if ($this->name !== null) {
            $config['name'] = $this->name;
        }
        if ($this->path !== null) {
            $config['path'] = $this->normalizePath($this->path);
        }
        if ($this->routeNamePrefix !== null) {
            $config['route_name_prefix'] = $this->normalizeRouteNamePrefix($this->routeNamePrefix);
        }
        if ($this->guard !== null) {
            $config['guard'] = $this->guard;
        }
        if ($this->webMiddleware !== null) {
            $config['middleware']['web'] = $this->webMiddleware;
        }
        if ($this->authMiddleware !== null) {
            $config['middleware']['auth'] = $this->authMiddleware;
        }
        if ($this->loginPath !== null) {
            $config['auth']['login_path'] = $this->loginPath;
        }
        if ($this->logoutPath !== null) {
            $config['auth']['logout_path'] = $this->logoutPath;
        }
        if ($this->usernameField !== null) {
            $config['auth']['username'] = $this->usernameField;
        }
        if ($this->passwordField !== null) {
            $config['auth']['password'] = $this->passwordField;
        }
        if ($this->rememberKey !== null) {
            $config['auth']['remember_key'] = $this->rememberKey;
        }

        $config['discovery']['providers'] = self::mergeClasses(
            (array) ($config['discovery']['providers'] ?? []),
            $this->providerClasses,
        );
        $config['discovery']['resources'] = self::mergeClasses(
            (array) ($config['discovery']['resources'] ?? []),
            $this->resourceClasses,
        );
        $config['discovery']['pages'] = self::mergeClasses(
            (array) ($config['discovery']['pages'] ?? []),
            $this->pageClasses,
        );
        $config['discovery']['auto'] = [
            'enabled' => $this->autoDiscoveryEnabled,
            'targets' => array_map(
                static fn (DiscoveryTarget $target): array => $target->toArray(),
                $this->resolvedDiscoveryTargets(),
            ),
            'except' => $this->excludedDiscoveryClasses,
        ];

        if ($this->reportBoot !== null) {
            $config['logging']['report_boot'] = $this->reportBoot;
        }

        return $config;
    }

    public function reset(): static
    {
        $this->name = null;
        $this->path = null;
        $this->routeNamePrefix = null;
        $this->guard = null;
        $this->webMiddleware = null;
        $this->authMiddleware = null;
        $this->loginPath = null;
        $this->logoutPath = null;
        $this->usernameField = null;
        $this->passwordField = null;
        $this->rememberKey = null;
        $this->providerClasses = [];
        $this->resourceClasses = [];
        $this->pageClasses = [];
        $this->discoveryTargets = [];
        $this->excludedDiscoveryClasses = [];
        $this->autoDiscoveryEnabled = true;
        $this->reportBoot = null;

        return $this;
    }

    private function addDiscoveryTarget(?string $directory, ?string $namespace, DiscoveryScope $scope): static
    {
        $this->discoveryTargets[] = DiscoveryTarget::make(
            directory: $directory ?? app_path('Flashboard'),
            namespace: $namespace ?? 'App\\Flashboard',
            scope: $scope,
        );
        $this->discoveryTargets = $this->uniqueDiscoveryTargets($this->discoveryTargets);

        return $this;
    }

    /**
     * @return list<DiscoveryTarget>
     */
    private function resolvedDiscoveryTargets(): array
    {
        if (!$this->autoDiscoveryEnabled) {
            return [];
        }
        if ($this->discoveryTargets !== []) {
            return $this->discoveryTargets;
        }

        return [
            DiscoveryTarget::make(
                directory: app_path('Flashboard'),
                namespace: 'App\\Flashboard',
            ),
        ];
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function normalizeValues(array $values): array
    {
        return array_values(array_filter(
            $values,
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param list<class-string> $base
     * @param list<class-string> $extra
     *
     * @return list<class-string>
     */
    private static function mergeClasses(array $base, array $extra): array
    {
        return array_values(array_unique(array_merge($base, $extra)));
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? 'admin' : $path;
    }

    private function normalizeRouteNamePrefix(string $routeNamePrefix): string
    {
        $routeNamePrefix = trim($routeNamePrefix);

        if ($routeNamePrefix === '') {
            return 'flashboard.';
        }

        return str_ends_with($routeNamePrefix, '.') ? $routeNamePrefix : $routeNamePrefix . '.';
    }

    /**
     * @param list<DiscoveryTarget> $targets
     *
     * @return list<DiscoveryTarget>
     */
    private function uniqueDiscoveryTargets(array $targets): array
    {
        $serialized = [];
        $uniqueTargets = [];

        foreach ($targets as $target) {
            $key = implode('|', [
                $target->directory(),
                $target->namespace(),
                $target->scope()->value,
            ]);

            if (isset($serialized[$key])) {
                continue;
            }

            $serialized[$key] = true;
            $uniqueTargets[] = $target;
        }

        return $uniqueTargets;
    }
}
