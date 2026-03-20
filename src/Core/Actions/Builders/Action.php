<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Actions\Builders;

use Closure;
use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutionResult;

final class Action implements ActionContract
{
    private ?string $label = null;

    private ?string $icon = null;

    private ?string $color = null;

    private bool $requiresConfirmation = false;

    private ?string $modal = null;

    private bool $visible = true;

    private ?Closure $handler = null;

    private ?string $successMessage = null;

    private ?string $redirectTo = null;

    private function __construct(
        private readonly string $key,
    ) {
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function requiresConfirmation(bool $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    public function modal(?string $modal): static
    {
        $this->modal = $modal;

        return $this;
    }

    public function visible(bool $condition): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function handleUsing(?callable $handler): static
    {
        $this->handler = $handler === null ? null : Closure::fromCallable($handler);

        return $this;
    }

    public function successMessage(?string $message): static
    {
        $this->successMessage = $message;

        return $this;
    }

    public function redirectTo(?string $url): static
    {
        $this->redirectTo = $url;

        return $this;
    }

    public function execute(array $arguments = []): ActionExecutionResult
    {
        $data = [];

        if (is_callable($this->handler)) {
            $result = ($this->handler)($arguments);

            if (is_array($result)) {
                $data = $result;
            }
        }

        return new ActionExecutionResult(
            successful: true,
            message: $this->successMessage,
            redirectTo: $this->redirectTo,
            data: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?? str($this->key)->headline()->toString(),
            'icon' => $this->icon,
            'color' => $this->color,
            'requires_confirmation' => $this->requiresConfirmation,
            'modal' => $this->modal,
            'visible' => $this->visible,
            'success_message' => $this->successMessage,
            'redirect_to' => $this->redirectTo,
            'has_handler' => $this->handler !== null,
        ];
    }
}
