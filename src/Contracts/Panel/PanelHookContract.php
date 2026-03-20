<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Panel;

interface PanelHookContract
{
    public function stage(): PanelLifecycleStage;

    public function handle(PanelDefinitionContract $panel): void;
}
