<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Panel;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;

final readonly class PanelShell
{
    public function __construct(
        private string $title,
        private string $view,
        private string $component,
    ) {
    }

    public static function placeholder(PanelDefinitionContract $panel): self
    {
        return new self(
            title: $panel->name(),
            view: 'flashboard::panel',
            component: 'Flashboard/Screen',
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function view(): string
    {
        return $this->view;
    }

    public function component(): string
    {
        return $this->component;
    }
}
