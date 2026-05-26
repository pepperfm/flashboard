<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Actions;

use Pepperfm\Flashboard\Contracts\Tables\TableActionContract;

final class TableAction implements TableActionContract
{
    public const string KEY_VIEW = 'view';

    public const string KEY_EDIT = 'edit';

    public const string KEY_DELETE = 'delete';

    public const string KIND_BUILT_IN = 'built_in';

    public const string KIND_CUSTOM = 'custom';

    public const string METHOD_GET = 'get';

    public const string METHOD_POST = 'post';

    public const string METHOD_PUT = 'put';

    public const string METHOD_PATCH = 'patch';

    public const string METHOD_DELETE = 'delete';

    private ?string $label = null;

    private ?string $icon = null;

    private ?string $color = null;

    private string $method = self::METHOD_GET;

    private bool $requiresConfirmation = false;

    private bool $visible = true;

    private string $kind = self::KIND_CUSTOM;

    private function __construct(
        private readonly string $key,
    ) {
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public static function view(): static
    {
        return static::make(self::KEY_VIEW)
            ->label('View')
            ->icon('i-lucide-eye')
            ->method(self::METHOD_GET)
            ->kind(self::KIND_BUILT_IN);
    }

    public static function edit(): static
    {
        return static::make(self::KEY_EDIT)
            ->label('Edit')
            ->icon('i-lucide-pencil')
            ->method(self::METHOD_GET)
            ->kind(self::KIND_BUILT_IN);
    }

    public static function delete(): static
    {
        return static::make(self::KEY_DELETE)
            ->label('Delete')
            ->icon('i-lucide-trash-2')
            ->color('error')
            ->method(self::METHOD_DELETE)
            ->requiresConfirmation()
            ->kind(self::KIND_BUILT_IN);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(?string $label): static
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

    public function method(string $method): static
    {
        $method = strtolower($method);

        $this->method = in_array($method, [
            self::METHOD_GET,
            self::METHOD_POST,
            self::METHOD_PUT,
            self::METHOD_PATCH,
            self::METHOD_DELETE,
        ], true) ? $method : self::METHOD_GET;

        return $this;
    }

    public function requiresConfirmation(bool $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    public function visible(bool $condition): static
    {
        $this->visible = $condition;

        return $this;
    }

    public function kind(string $kind): static
    {
        $this->kind = $kind === self::KIND_BUILT_IN ? self::KIND_BUILT_IN : self::KIND_CUSTOM;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?? str($this->key)->headline()->toString(),
            'icon' => $this->icon,
            'color' => $this->color,
            'method' => $this->method,
            'requires_confirmation' => $this->requiresConfirmation,
            'visible' => $this->visible,
            'kind' => $this->kind,
        ];
    }
}
