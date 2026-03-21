<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Core\Detail\Builders;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;
use Pepperfm\Flashboard\Contracts\Schema\KeyedSchemaNodeContract;
use Pepperfm\Flashboard\Contracts\Detail\DetailContract;
use Pepperfm\Flashboard\Core\Detail\Normalization\DetailSchemaNormalizer;

final class Detail implements DetailContract
{
    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $sections = [];

    /**
     * @var list<array<string, mixed>|KeyedSchemaNodeContract>
     */
    private array $entries = [];

    /**
     * @var list<ActionContract|array<string, mixed>>
     */
    private array $actions = [];

    /**
     * @var list<ActionContract|array<string, mixed>>
     */
    private array $headerActions = [];

    public static function make(): self
    {
        return new self();
    }

    public function sections(array $sections): static
    {
        $this->sections = $sections;

        return $this;
    }

    public function entries(array $entries): static
    {
        $this->entries = $entries;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function headerActions(array $headerActions): static
    {
        $this->headerActions = $headerActions;

        return $this;
    }

    public function toArray(): array
    {
        return (new DetailSchemaNormalizer())->normalize([
            'sections' => $this->sections,
            'entries' => $this->entries,
            'actions' => $this->normalizeActions($this->actions),
            'header_actions' => $this->normalizeActions($this->headerActions),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entrySchema(): array
    {
        return (array) $this->toArray()['entries'];
    }

    /**
     * @param list<ActionContract|array<string, mixed>> $actions
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeActions(array $actions): array
    {
        return array_values(array_map(
            static fn(ActionContract|array $action
            ): array => $action instanceof ActionContract ? $action->toArray() : $action,
            $actions,
        ));
    }
}
