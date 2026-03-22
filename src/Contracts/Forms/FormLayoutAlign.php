<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FormLayoutAlign: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';
    case Stretch = 'stretch';
}
