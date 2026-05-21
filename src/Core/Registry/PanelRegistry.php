<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Registry;

use Illuminate\Container\Attributes\Singleton;
use Pepperfm\Flashboard\Contracts\Panel\PanelDefinitionContract;
use Pepperfm\Flashboard\Contracts\Panel\PanelProviderContract;

#[Singleton]
final class PanelRegistry
{
    /**
     * @var array<class-string<PanelProviderContract>, PanelProviderContract>
     */
    private array $providers = [];

    public function register(PanelProviderContract $provider): void
    {
        $this->providers[$provider::class] = $provider;
    }

    /**
     * @return list<PanelProviderContract>
     */
    public function providers(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return list<PanelDefinitionContract>
     */
    public function panels(): array
    {
        return array_values(array_map(
            static fn (PanelProviderContract $provider): PanelDefinitionContract => $provider->panel(),
            $this->providers(),
        ));
    }
}
