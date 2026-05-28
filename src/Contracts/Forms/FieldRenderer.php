<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Forms;

enum FieldRenderer: string
{
    case Checkbox = 'checkbox';
    case Date = 'date';
    case FileUpload = 'file_upload';
    case Input = 'input';
    case RichText = 'rich_text';
    case Select = 'select';
    case Switch = 'switch';
    case Textarea = 'textarea';
}
