<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FormLayoutMode: string
{
    case Stack = 'stack';
    case Grid = 'grid';
    case Flex = 'flex';
}
