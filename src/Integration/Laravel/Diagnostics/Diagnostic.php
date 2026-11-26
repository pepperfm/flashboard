<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Diagnostics;

final readonly class Diagnostic
{
    public function __construct(
        public string $code,
        public DiagnosticSeverity $severity,
        public string $message,
        public ?string $resource = null
    ) {
    }

    /**
     * @return array{code: string, severity: string, message: string, resource: ?string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'resource' => $this->resource,
        ];
    }
}
