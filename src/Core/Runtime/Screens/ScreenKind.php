<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Screens;

enum ScreenKind: string
{
    case Page = 'page';
    case Resource = 'resource';
}
