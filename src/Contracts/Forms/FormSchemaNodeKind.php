<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FormSchemaNodeKind: string
{
    case Field = 'field';
    case Section = 'section';
    case Tabs = 'tabs';
    case Tab = 'tab';
}
