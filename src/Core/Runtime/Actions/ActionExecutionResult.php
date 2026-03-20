<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Actions;

final readonly class ActionExecutionResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private bool $successful,
        private ?string $message,
        private ?string $redirectTo,
        private array $data = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'message' => $this->message,
            'redirect_to' => $this->redirectTo,
            'data' => $this->data,
        ];
    }
}
