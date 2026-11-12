<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Runtime;

use Pepperfm\Flashboard\Core\Runtime\Context\RuntimeRequestContext;

final readonly class PageComponentContextFactory
{
    /**
     * @return array{
     *     metadata: array<string, mixed>,
     *     query: array<string, mixed>,
     *     route_parameters: array<string, mixed>,
     *     page_class: string|null,
     *     page_key: string,
     *     user_id: mixed
     * }
     */
    public function make(
        RuntimeRequestContext $context,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): array {
        return [
            'metadata' => $context->metadata()->toArray(),
            'query' => $this->normalizeArray((array) $context->request()->query()),
            'route_parameters' => $this->routeParameters($context->request()->route()),
            'page_class' => $context->screen()->pageClass(),
            'page_key' => $context->screen()->key(),
            'user_id' => $user?->getAuthIdentifier(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routeParameters(mixed $route): array
    {
        if (!$route instanceof \Illuminate\Routing\Route) {
            return [];
        }

        return $this->normalizeArray($route->parameters());
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, mixed>
     */
    private function normalizeArray(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            return $value->getRouteKey();
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
