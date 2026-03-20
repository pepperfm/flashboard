<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Pages;

enum PageType: string
{
    case Dashboard = 'dashboard';
    case ResourceIndex = 'resource_index';
    case ResourceCreate = 'resource_create';
    case ResourceEdit = 'resource_edit';
    case ResourceDetail = 'resource_detail';
    case Custom = 'custom';
}
