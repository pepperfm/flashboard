<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Inspection;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Registry\ResourceRegistry;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\Diagnostic;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\PanelDoctor;

final readonly class PanelInspector
{
    public function __construct(
        private PanelDefinitionContract $panel,
        private ResourceRegistry $registry,
        private PanelDoctor $doctor,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $resources = $this->registry->all();

        return [
            'path' => $this->panel->path(),
            'route_name_prefix' => $this->panel->routeNamePrefix(),
            'resource_count' => count($resources),
            'resources' => array_map(static fn(string $resource): array => [
                'class' => $resource,
                'key' => $resource::key(),
                'model' => $resource::model(),
            ], array_slice($resources, 0, 200)),
            'diagnostics' => array_map(static fn(Diagnostic $diagnostic): array => $diagnostic->toArray(), array_slice($this->doctor->inspect(), 0, 500)),
            'runtime_authorization' => 'not_evaluated',
        ];
    }
}
