<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Integration\Laravel\Discovery;

use Illuminate\Support\Arr;
use Pepperfm\Flashboard\Flashboard;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelHookContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Core\Pages\DashboardPage;

final readonly class ConfigPanelProvider implements PanelProviderContract
{
    /**
     * @var list<class-string<PageDefinitionContract>>
     */
    public const array DEFAULT_PAGE_CLASSES = [
        DashboardPage::class,
    ];

    public function __construct(
        private PanelDefinitionContract $panel,
    ) {
    }

    public function panel(): PanelDefinitionContract
    {
        return $this->panel;
    }

    /**
     * @return list<class-string<Resource>>
     */
    public function resources(): array
    {
        $config = Flashboard::resolvedConfig((array) config('flashboard', []));

        return $this->normalizeClasses(
            (array) Arr::get($config, 'discovery.resources', []),
        );
    }

    /**
     * @return list<class-string<PageDefinitionContract>>
     */
    public function pages(): array
    {
        $config = Flashboard::resolvedConfig((array) config('flashboard', []));

        return array_values(array_unique(array_merge(
            self::DEFAULT_PAGE_CLASSES,
            $this->normalizeClasses(
                (array) Arr::get($config, 'discovery.pages', []),
            ),
        )));
    }

    /**
     * @return list<PanelHookContract>
     */
    public function hooks(): array
    {
        return [];
    }

    /**
     * @param array<int, mixed> $classes
     *
     * @return list<class-string>
     */
    private function normalizeClasses(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            static fn(mixed $class): bool => is_string($class) && $class !== '',
        ));
    }
}
