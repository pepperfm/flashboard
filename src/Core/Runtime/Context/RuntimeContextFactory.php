<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Context;

use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelLifecycleStage;
use Pepperfm\Flashboard\Core\Runtime\Lifecycle\LifecycleManager;
use Pepperfm\Flashboard\Core\Runtime\Metadata\RuntimeMetadataFactory;
use Pepperfm\Flashboard\Core\Runtime\Resolvers\ScreenResolver;

final readonly class RuntimeContextFactory
{
    public function __construct(
        private ScreenResolver $screenResolver,
        private RuntimeMetadataFactory $metadataFactory,
        private LifecycleManager $lifecycleManager,
    ) {
    }

    public function make(\Illuminate\Http\Request $request, PanelDefinitionContract $panel): RuntimeRequestContext
    {
        $this->lifecycleManager->run(PanelLifecycleStage::Booting, $panel);

        $screen = $this->screenResolver->resolve($request, $panel);
        $metadata = $this->metadataFactory->make($panel, $screen);
        $context = new RuntimeRequestContext(
            request: $request,
            panel: $panel,
            screen: $screen,
            metadata: $metadata,
        );

        $this->lifecycleManager->run(PanelLifecycleStage::Booted, $panel);

        return $context;
    }
}
