<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\Diagnostic;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\DiagnosticSeverity;
use Pepperfm\Flashboard\Integration\Laravel\Diagnostics\PanelDoctor;

#[Signature('flashboard:doctor {--json : Output machine-readable diagnostics}')]
#[Description('Inspect Flashboard configuration, resources and published assets without changing application data.')]
final class DoctorCommand extends Command
{
    public function handle(PanelDoctor $doctor): int
    {
        $diagnostics = $doctor->inspect();

        if ($this->option('json')) {
            $items = array_map(static fn(Diagnostic $diagnostic): array => $diagnostic->toArray(), $diagnostics);
            $this->line(json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Status', 'Check', 'Resource', 'Message'],
                array_map(static fn(Diagnostic $diagnostic): array => [
                    strtoupper($diagnostic->severity->value),
                    $diagnostic->code,
                    $diagnostic->resource ?? '',
                    $diagnostic->message,
                ], $diagnostics)
            );
        }

        return array_any($diagnostics, static fn(Diagnostic $diagnostic): bool => $diagnostic->severity === DiagnosticSeverity::Error)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
