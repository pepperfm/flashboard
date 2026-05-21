<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Tables\Filters;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class SelectFilterOptionsQuery
{
    /**
     * @param class-string $resourceClass
     * @param list<string|int|bool> $selectedValues
     */
    public function __construct(
        public string $resourceClass,
        public string $filterKey,
        public string $queryColumn,
        public string $search,
        public int $page,
        public int $perPage,
        public mixed $selected = null,
        public ?Authenticatable $user = null,
        public ?object $request = null,
        public array $selectedValues = [],
    ) {
    }

    public function offset(): int
    {
        return max(0, ($this->page - 1) * $this->perPage);
    }
}
