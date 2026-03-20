<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Payloads;

final readonly class FormPayload
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        private array $schema,
    ) {
    }

    public function toArray(): array
    {
        return $this->schema;
    }
}
