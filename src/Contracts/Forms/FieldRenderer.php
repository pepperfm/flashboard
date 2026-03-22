<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FieldRenderer: string
{
    case Input = 'input';
    case Textarea = 'textarea';
    case Select = 'select';
    case Switch = 'switch';
}
