<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Panel;

interface PanelDefinitionContract
{
    public function name(): string;

    public function path(): string;

    public function routeNamePrefix(): string;

    public function guard(): ?string;

    /**
     * @return list<string>
     */
    public function webMiddleware(): array;

    /**
     * @return list<string>
     */
    public function authMiddleware(): array;
}
