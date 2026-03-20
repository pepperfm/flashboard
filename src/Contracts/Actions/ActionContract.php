<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Actions;

interface ActionContract
{
    public static function make(string $key): static;

    public function key(): string;

    public function label(string $label): static;

    public function icon(?string $icon): static;

    public function color(?string $color): static;

    public function requiresConfirmation(bool $condition = true): static;

    public function modal(?string $modal): static;

    public function visible(bool $condition): static;

    public function handleUsing(?callable $handler): static;

    public function successMessage(?string $message): static;

    public function redirectTo(?string $url): static;

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments = []): \Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutionResult;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
