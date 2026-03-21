<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Resources;

enum ResourceSurface: string
{
    case Table = 'table';
    case Form = 'form';
    case Detail = 'detail';
    case Infolist = 'infolist';
    case Actions = 'actions';
    case Pages = 'pages';
}
