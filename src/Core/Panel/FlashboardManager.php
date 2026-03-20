<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Panel;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;

final readonly class FlashboardManager
{
    public function __construct(
        private PanelDefinitionContract $panel,
    ) {
    }

    public function panel(): PanelDefinitionContract
    {
        return $this->panel;
    }
}
