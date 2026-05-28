<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Tables\Filters;

use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsQuery;
use Pepperfm\Flashboard\Contracts\Tables\Filters\SelectFilterOptionsResult;

class SelectFilter extends Filter
{
    public const int DEFAULT_OPTIONS_PER_PAGE = 15;

    private ?\Closure $lazyOptionsResolver = null;

    public static function make(string $key, ?string $label = null): static
    {
        return parent::make($key, $label)->type('select');
    }

    public function lazy(?\Closure $resolver = null, ?int $perPage = null): static
    {
        $this->lazyOptionsResolver = $resolver;

        return $this
            ->attribute('lazy', true)
            ->attribute('options_per_page', max(1, $perPage ?? self::DEFAULT_OPTIONS_PER_PAGE));
    }

    public function optionLabel(string $column): static
    {
        return $this->attribute('option_label_column', $column);
    }

    public function optionValue(string $column): static
    {
        return $this
            ->queryColumn($column)
            ->attribute('option_value_column', $column);
    }

    public function multiple(bool $condition = true): static
    {
        if (!$condition) {
            return $this;
        }

        return $this->attribute('multiple', true);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function options(array $options): static
    {
        return parent::options($options);
    }

    public function isLazy(): bool
    {
        return (bool) ($this->toArray()['lazy'] ?? false);
    }

    public function hasLazyOptionsResolver(): bool
    {
        return $this->lazyOptionsResolver instanceof \Closure;
    }

    public function resolveLazyOptions(SelectFilterOptionsQuery $query): ?SelectFilterOptionsResult
    {
        if (!$this->lazyOptionsResolver instanceof \Closure) {
            return null;
        }

        $result = ($this->lazyOptionsResolver)($query);

        if ($result instanceof SelectFilterOptionsResult) {
            return $result;
        }

        if (is_array($result)) {
            return SelectFilterOptionsResult::make($result);
        }

        throw new \UnexpectedValueException('Lazy select filter resolver must return a SelectFilterOptionsResult or an array of options.');
    }
}
