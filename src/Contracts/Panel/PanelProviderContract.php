<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Panel;

use Pepperfm\Flashboard\Contracts\Pages\PageDefinitionContract;
use Pepperfm\Flashboard\Contracts\Resources\Resource;

interface PanelProviderContract
{
    public function panel(): PanelDefinitionContract;

    /**
     * @return list<class-string<Resource>>
     */
    public function resources(): array;

    /**
     * @return list<class-string<PageDefinitionContract>>
     */
    public function pages(): array;

    /**
     * @return list<PanelHookContract>
     */
    public function hooks(): array;
}
