<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Panel;

final readonly class DiscoveryTarget
{
    public function __construct(
        private string $directory,
        private string $namespace,
        private DiscoveryScope $scope,
    ) {
    }

    public static function make(
        string $directory,
        string $namespace,
        DiscoveryScope $scope = DiscoveryScope::Both
    ): self {
        return new self(
            directory: rtrim($directory, '/'),
            namespace: trim($namespace, '\\'),
            scope: $scope,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $scope = $data['scope'] ?? DiscoveryScope::Both->value;

        return self::make(
            directory: (string) ($data['directory'] ?? ''),
            namespace: (string) ($data['namespace'] ?? ''),
            scope: DiscoveryScope::from((string) $scope),
        );
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function scope(): DiscoveryScope
    {
        return $this->scope;
    }

    public function discoversResources(): bool
    {
        return $this->scope->discoversResources();
    }

    public function discoversPages(): bool
    {
        return $this->scope->discoversPages();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'directory' => $this->directory,
            'namespace' => $this->namespace,
            'scope' => $this->scope->value,
        ];
    }
}
