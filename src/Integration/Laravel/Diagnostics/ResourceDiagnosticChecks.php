<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Diagnostics;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Contracts\Actions\ContextualActionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final readonly class ResourceDiagnosticChecks
{
    public function __construct(
        private ResourceRegistry $registry,
        private Gate $gate,
        private Router $router,
        private PanelDefinitionContract $panel
    ) {
    }

    /**
     * @return list<Diagnostic>
     */
    public function inspect(): array
    {
        $resources = $this->registry->all();
        if ($resources === []) {
            return [new Diagnostic('resources', DiagnosticSeverity::Warning, 'No resources are registered. Check resource discovery if this is unexpected.')];
        }

        $keys = [];
        $paths = [];
        $diagnostics = [];

        foreach ($resources as $resource) {
            try {
                $key = $resource::key();
                $path = trim($resource::routeBasePath(), '/');

                if (isset($keys[$key]) || isset($paths[$path])) {
                    $diagnostics[] = new Diagnostic('resource_conflict', DiagnosticSeverity::Error, 'Resource key or route path conflicts with another registered resource.', $resource);
                }

                $keys[$key] = true;
                $paths[$path] = true;
                $diagnostics = [...$diagnostics, $this->route($resource), ...$this->inspectResource($resource)];
            } catch (\Throwable) {
                $diagnostics[] = new Diagnostic('resource_inspection_failed', DiagnosticSeverity::Error, 'Resource metadata could not be inspected. Review its declarations.', $resource);
            }
        }

        return [
            new Diagnostic('resources', DiagnosticSeverity::Ok, count($resources).' resources discovered.'),
            ...$diagnostics,
        ];
    }

    /**
     * @param class-string<Resource> $resource
     * @return list<Diagnostic>
     */
    public function inspectRegisteredResource(string $resource): array
    {
        $checks = [$this->route($resource), ...$this->inspectResource($resource)];

        foreach ($this->registry->all() as $candidate) {
            if ($candidate !== $resource && ($candidate::key() === $resource::key()
                || trim($candidate::routeBasePath(), '/') === trim($resource::routeBasePath(), '/'))) {
                $checks[] = new Diagnostic('resource_conflict', DiagnosticSeverity::Error, 'Resource key or route path conflicts with another registered resource.', $resource);
                break;
            }
        }

        return $checks;
    }

    /** @param class-string<Resource> $resource */
    private function route(string $resource): Diagnostic
    {
        $name = $this->panel->routeNamePrefix().'resources.'.$resource::key().'.index';
        $route = $this->router->getRoutes()->getByName($name);
        $matches = $route !== null && ($route->defaults['flashboard.resource'] ?? null) === $resource;

        return $matches
            ? new Diagnostic('resource_route', DiagnosticSeverity::Ok, 'Resource index route is registered.', $resource)
            : new Diagnostic('resource_route_missing', DiagnosticSeverity::Error, 'Resource index route is missing or conflicts with another route.', $resource);
    }

    /**
     * @param class-string<Resource> $resource
     * @return list<Diagnostic>
     */
    public function inspectResource(string $resource): array
    {
        $model = $resource::model();

        if (!is_a($model, Model::class, true)) {
            return [new Diagnostic('resource_model', DiagnosticSeverity::Error, 'Resource model must extend Eloquent Model.', $resource)];
        }

        $policy = $resource::policy();
        $abilities = $this->abilities($resource);

        if ($policy === null) {
            if ($abilities !== []) {
                return [new Diagnostic('resource_policy_missing', DiagnosticSeverity::Error, 'Explicit resource abilities require a configured and registered policy.', $resource)];
            }

            return [new Diagnostic('resource_policy_optional', DiagnosticSeverity::Warning, 'No resource policy is configured. Verify this is intentional; panel access alone may grant broad access.', $resource)];
        }

        if (!$this->gate->getPolicyFor($model) instanceof $policy) {
            return [new Diagnostic('resource_policy_unregistered', DiagnosticSeverity::Error, 'Configured resource policy is not registered for its model.', $resource)];
        }

        foreach ($abilities as $ability) {
            $method = str_contains($ability, '-') ? str($ability)->camel()->value() : $ability;

            if (!method_exists($policy, $method) || !new \ReflectionMethod($policy, $method)->isPublic()) {
                return [new Diagnostic('resource_ability_missing', DiagnosticSeverity::Error, 'A mapped resource ability has no corresponding policy method.', $resource)];
            }
        }

        return [new Diagnostic('resource_policy', DiagnosticSeverity::Ok, 'Resource policy and explicit ability methods resolve; authorization results remain runtime-dependent.', $resource)];
    }

    /**
     * @param class-string<Resource> $resource
     *
     * @return list<string>
     */
    private function abilities(string $resource): array
    {
        $abilities = [
            ...array_values($resource::actionAbilityMap()),
            ...array_values($resource::fieldAbilityMap()),
            ...array_values($resource::relationAbilityMap()),
            ...array_values($resource::metricAbilityMap()),
        ];

        foreach ($resource::actions() as $action) {
            if ($action instanceof ContextualActionContract && $action->requiredAbility() !== null) {
                $abilities[] = $action->requiredAbility();
            }
        }

        return array_values(array_unique($abilities));
    }
}
