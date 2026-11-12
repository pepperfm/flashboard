<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai;

final class ResourceChangeManager
{
    public function __construct(
        private readonly ResourceManager $resources,
    ) {
    }

    /**
     * Read the specification of an unchanged AI-managed Resource only.
     *
     * @return array<string, mixed>
     */
    public function specification(string $resource): array
    {
        return $this->resources->managedSpecification($resource);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function previewField(string $resource, array $definition): array
    {
        return $this->preview($resource, 'fields', $definition);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function previewRelation(string $resource, array $definition): array
    {
        return $this->preview($resource, 'relations', $definition);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function addField(string $resource, array $definition, string $expectedSha, string $proposalHash): array
    {
        return $this->apply($resource, 'fields', $definition, $expectedSha, $proposalHash);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function addRelation(string $resource, array $definition, string $expectedSha, string $proposalHash): array
    {
        return $this->apply($resource, 'relations', $definition, $expectedSha, $proposalHash);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function preview(string $resource, string $collection, array $definition): array
    {
        $spec = $this->withAddedDefinition($resource, $collection, $definition);
        $preview = $this->resources->preview($spec);

        if ($preview['operation'] !== 'update' || !$preview['writable']) {
            throw new \RuntimeException('Only unchanged, AI-managed Resources support incremental updates.');
        }

        return $preview + ['specification' => $spec];
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function apply(
        string $resource,
        string $collection,
        array $definition,
        string $expectedSha,
        string $proposalHash,
    ): array {
        if (!preg_match('/^[a-f0-9]{64}$/D', $expectedSha)
            || !preg_match('/^[a-f0-9]{64}$/D', $proposalHash)) {
            throw new \InvalidArgumentException('Both hashes from a fresh preview are required to write.');
        }

        $spec = $this->withAddedDefinition($resource, $collection, $definition);

        return $this->resources->update($spec, $expectedSha, $proposalHash);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function withAddedDefinition(string $resource, string $collection, array $definition): array
    {
        $spec = $this->resources->managedSpecification($resource);
        $name = $definition['name'] ?? null;

        if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new \InvalidArgumentException('An existing column or relation name is required.');
        }

        if (!is_array($spec[$collection] ?? null)) {
            throw new \RuntimeException("Managed Resource specification is missing [$collection].");
        }

        foreach (['fields', 'relations'] as $kind) {
            foreach ($spec[$kind] ?? [] as $existing) {
                if (is_array($existing) && ($existing['name'] ?? null) === $name) {
                    throw new \InvalidArgumentException("Resource already declares field or relation [$name].");
                }
            }
        }

        $spec[$collection][] = $definition;

        return $spec;
    }
}
