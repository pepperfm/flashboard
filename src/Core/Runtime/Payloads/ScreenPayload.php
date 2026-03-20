<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Payloads;

final readonly class ScreenPayload
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    public function toArray(): array
    {
        return [
            'schema_version' => SchemaVersion::V1->value,
            ...$this->data,
        ];
    }
}
