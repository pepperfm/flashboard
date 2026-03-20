<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Panel;

enum PanelLifecycleStage: string
{
    case Booting = 'booting';
    case Booted = 'booted';
    case ResolvingNavigation = 'resolving_navigation';
    case ResolvingPage = 'resolving_page';
}
