<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Lifecycle;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelLifecycleStage;
use Pepperfm\Flashboard\Core\Registry\PanelRegistry;

#[Singleton]
final readonly class LifecycleManager
{
    public function __construct(
        private PanelRegistry $panelRegistry,
    ) {
    }

    public function run(PanelLifecycleStage $stage, PanelDefinitionContract $panel): void
    {
        foreach ($this->panelRegistry->providers() as $provider) {
            foreach ($provider->hooks() as $hook) {
                if ($hook->stage() !== $stage) {
                    continue;
                }

                $hook->handle($panel);
            }
        }
    }
}
