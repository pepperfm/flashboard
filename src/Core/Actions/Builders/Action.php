<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Actions\Builders;

use Pepperfm\Flashboard\Contracts\Actions\ActionScope;
use Pepperfm\Flashboard\Contracts\Actions\FormActionContract;
use Pepperfm\Flashboard\Contracts\Forms\FormContract;
use Pepperfm\Flashboard\Core\Runtime\Actions\ActionExecutionResult;

final class Action implements FormActionContract
{
    private ?string $label = null;

    private ?string $icon = null;

    private ?string $color = null;

    private bool $requiresConfirmation = false;

    private ?string $modal = null;

    private bool $visible = true;

    private ?\Closure $handler = null;

    private ActionScope $scope = ActionScope::Legacy;

    private ?string $ability = null;

    private ?\Closure $visibility = null;

    private ?\Closure $authorization = null;

    private FormContract|\Closure|null $form = null;

    private ?string $modalDescription = null;

    public function form(FormContract|callable $form): static
    {
        $this->form = $form instanceof FormContract ? $form : $form(...);

        return $this;
    }

    public function hasForm(): bool
    {
        return $this->form !== null;
    }

    public function resolveForm(array $context): ?FormContract
    {
        return $this->form instanceof \Closure ? value($this->form, $context) : $this->form;
    }

    public function modalDescription(?string $description): static
    {
        $this->modalDescription = $description;

        return $this;
    }

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

    public function scope(ActionScope $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function actionScope(): ActionScope
    {
        return $this->scope;
    }

    public function ability(?string $ability): static
    {
        $this->ability = $ability;

        return $this;
    }

    public function requiredAbility(): ?string
    {
        return $this->ability;
    }

    public function visibleWhen(callable $condition): static
    {
        $this->visibility = $condition(...);

        return $this;
    }

    public function authorizeUsing(callable $condition): static
    {
        $this->authorization = $condition(...);

        return $this;
    }

    public function isVisible(array $context): bool
    {
        return $this->visible && ($this->visibility === null || ($this->visibility)($context) === true);
    }

    public function isAuthorized(array $context): bool
    {
        return $this->authorization === null || ($this->authorization)($context) === true;
    }

    public function handleUsing(?callable $handler): static
    {
        $this->handler = $handler === null ? null : $handler(...);

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
            $result = value($this->handler, $arguments);
            if ($result instanceof ActionExecutionResult) {
                return $result;
            }
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
            'modal_description' => $this->modalDescription,
            'has_form' => $this->hasForm(),
            'visible' => $this->visible,
            'scope' => $this->scope->value,
            'success_message' => $this->successMessage,
            'redirect_to' => $this->redirectTo,
            'has_handler' => $this->handler !== null,
        ];
    }
}
