<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Diagnostics;

enum DiagnosticSeverity: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';
}
