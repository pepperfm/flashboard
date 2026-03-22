<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FormLayoutJustify: string
{
    case Start = 'start';
    case Center = 'center';
    case End = 'end';
    case Between = 'between';
}
