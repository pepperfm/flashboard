<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Runtime\Screens;

use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

final readonly class ResolvedScreen
{
    /**
     * @param class-string<PageDefinitionContract>|null $pageClass
     * @param class-string<Resource>|null $resourceClass
     */
    public function __construct(
        private ScreenKind $kind,
        private string $key,
        private string $routeName,
        private ?string $pageClass,
        private ?string $resourceClass,
    ) {
    }

    public function kind(): ScreenKind
    {
        return $this->kind;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function routeName(): string
    {
        return $this->routeName;
    }

    /**
     * @return class-string<PageDefinitionContract>|null
     */
    public function pageClass(): ?string
    {
        return $this->pageClass;
    }

    /**
     * @return class-string<Resource>|null
     */
    public function resourceClass(): ?string
    {
        return $this->resourceClass;
    }
}
