<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\UI\Theme;

use Pepperfm\Flashboard\UI\Contracts\UiPayloadContract;

final readonly class ThemePreset implements UiPayloadContract
{
    public function __construct(
        private string $name,
        private array $tokens,
    ) {
    }

    public static function default(): self
    {
        return new self('archive-sand', [
            'accent' => '#2d241c',
            'background' => '#f4efe2',
            'muted' => '#75624d',
            'surface' => '#fffaf1',
        ]);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tokens' => $this->tokens,
        ];
    }
}
