<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Context;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Core\Runtime\Metadata\RuntimeMetadata;
use Pepperfm\Flashboard\Core\Runtime\Screens\ResolvedScreen;

final readonly class RuntimeRequestContext
{
    public function __construct(
        private \Illuminate\Http\Request $request,
        private PanelDefinitionContract $panel,
        private ResolvedScreen $screen,
        private RuntimeMetadata $metadata,
    ) {
    }

    public function request(): \Illuminate\Http\Request
    {
        return $this->request;
    }

    public function panel(): PanelDefinitionContract
    {
        return $this->panel;
    }

    public function screen(): ResolvedScreen
    {
        return $this->screen;
    }

    public function metadata(): RuntimeMetadata
    {
        return $this->metadata;
    }
}
