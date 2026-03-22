<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FormLayoutDirection: string
{
    case Row = 'row';
    case Column = 'column';
}
