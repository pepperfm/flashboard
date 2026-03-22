<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Resources;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;
use Pepperfm\Flashboard\Contracts\Resources\ResourceSurface;
use Pepperfm\Flashboard\Core\Authorization\Visibility\ScreenAccessResolver;
use Pepperfm\Flashboard\Core\Detail\Builders\Detail;
use Pepperfm\Flashboard\Core\Forms\Builders\Form;
use Pepperfm\Flashboard\Core\Tables\Builders\Table;

final readonly class ResourceSurfaceResolver
{
    public function __construct(
        private ScreenAccessResolver $screenAccessResolver,
    ) {
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return array<string, bool>
     */
    public function availability(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): array {
        return [
            ResourceSurface::Table->value => $this->hasTableSurface($resourceClass),
            ResourceSurface::Form->value => $this->hasFormSurface($resourceClass),
            ResourceSurface::Detail->value => $this->hasDetailSurface($resourceClass),
            ResourceSurface::Infolist->value => $this->hasDetailSurface($resourceClass),
            ResourceSurface::Actions->value => $this->actions($resourceClass) !== [],
            ResourceSurface::Pages->value => $this->pages($resourceClass, $user) !== [],
        ];
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function hasDetailSurfaceForResource(string $resourceClass): bool
    {
        return $this->hasDetailSurface($resourceClass);
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<ActionContract>
     */
    public function actions(string $resourceClass): array
    {
        return array_values($resourceClass::actions());
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    public function findAction(string $resourceClass, string $actionKey): ?ActionContract
    {
        foreach ($this->actions($resourceClass) as $action) {
            if ($action->key() === $actionKey) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<class-string<PageDefinitionContract>>
     */
    public function pages(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): array {
        $pages = array_values($resourceClass::pages());

        if ($user === null) {
            return $pages;
        }

        return array_values(array_filter(
            $pages,
            fn(string $pageClass): bool => $this->screenAccessResolver->canAccessPage($pageClass, $user),
        ));
    }

    /**
     * @param class-string<Resource> $resourceClass
     *
     * @return list<array<string, mixed>>
     */
    public function pagePayloads(
        string $resourceClass,
        ?\Illuminate\Contracts\Auth\Authenticatable $user = null,
    ): array {
        return array_values(array_map(
            static fn(string $pageClass): array => [
                'class' => $pageClass,
                'key' => $pageClass::key(),
                'title' => $pageClass::title(),
                'type' => $pageClass::type()->value,
                'uri' => $pageClass::uri(),
                'navigation_label' => $pageClass::navigationLabel(),
                'navigation_group' => $pageClass::navigationGroup(),
                'is_navigable' => $pageClass::isNavigable(),
                'middleware' => $pageClass::middleware(),
            ],
            $this->pages($resourceClass, $user),
        ));
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function hasTableSurface(string $resourceClass): bool
    {
        $table = $resourceClass::table(Table::make())->toArray();

        return $table['columns'] !== []
            || $table['filters'] !== []
            || $table['scopes'] !== []
            || $table['actions'] !== []
            || $table['bulk_actions'] !== []
            || $table['pagination'] !== 15;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function hasFormSurface(string $resourceClass): bool
    {
        $form = $resourceClass::form(Form::make())->toArray();

        return $form['sections'] !== []
            || $form['tabs'] !== []
            || $form['fields'] !== []
            || $form['rules'] !== []
            || $form['defaults'] !== []
            || $form['has_mutate_data_using'] === true
            || $form['has_after_save'] === true;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function hasDetailSurface(string $resourceClass): bool
    {
        $detail = $resourceClass::detail(Detail::make())->toArray();

        return $detail['sections'] !== []
            || $detail['entries'] !== []
            || $detail['actions'] !== []
            || $detail['header_actions'] !== [];
    }
}
