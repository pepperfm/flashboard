<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Tables\Filters;

final readonly class SelectFilterOptionsResult
{
    private const string KEY_ITEMS = 'items';
    private const string KEY_META = 'meta';
    private const string KEY_HAS_MORE = 'has_more';
    private const string KEY_NEXT_PAGE = 'next_page';

    /**
     * @param list<array{label: string, value: string|int|bool}> $items
     */
    public function __construct(
        public array $items,
        public bool $hasMore = false,
        public ?int $nextPage = null,
    ) {
    }

    /**
     * @param list<array{label: string, value: string|int|bool}> $items
     */
    public static function make(array $items, bool $hasMore = false, ?int $nextPage = null): self
    {
        return new self($items, $hasMore, $nextPage);
    }

    /**
     * @return array{items: list<array{label: string, value: string|int|bool}>, meta: array{has_more: bool, next_page: int|null}}
     */
    public function toArray(): array
    {
        return [
            self::KEY_ITEMS => $this->items,
            self::KEY_META => [
                self::KEY_HAS_MORE => $this->hasMore,
                self::KEY_NEXT_PAGE => $this->nextPage,
            ],
        ];
    }
}
