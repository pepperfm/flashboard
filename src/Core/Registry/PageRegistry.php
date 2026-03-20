<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Registry;

use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;

final class PageRegistry
{
    /**
     * @var array<class-string<PageDefinitionContract>, class-string<PageDefinitionContract>>
     */
    private array $pages = [];

    /**
     * @param class-string<PageDefinitionContract> $pageClass
     */
    public function register(string $pageClass): void
    {
        if (! is_a($pageClass, PageDefinitionContract::class, true)) {
            throw new \InvalidArgumentException("Page [$pageClass] must implement ".PageDefinitionContract::class.'.');
        }

        $this->pages[$pageClass] = $pageClass;
    }

    /**
     * @param list<class-string<PageDefinitionContract>> $pageClasses
     */
    public function registerMany(array $pageClasses): void
    {
        foreach ($pageClasses as $pageClass) {
            $this->register($pageClass);
        }
    }

    /**
     * @return list<class-string<PageDefinitionContract>>
     */
    public function all(): array
    {
        return array_values($this->pages);
    }

    /**
     * @param class-string<PageDefinitionContract> $pageClass
     */
    public function has(string $pageClass): bool
    {
        return isset($this->pages[$pageClass]);
    }
}
