<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Detail;

use Pepperfm\Flashboard\Contracts\Actions\ActionContract;

interface DetailContract
{
    /**
     * @param list<array<string, mixed>> $sections
     */
    public function sections(array $sections): static;

    /**
     * @param list<array<string, mixed>> $entries
     */
    public function entries(array $entries): static;

    /**
     * @param list<ActionContract|array<string, mixed>> $actions
     */
    public function actions(array $actions): static;

    /**
     * @param list<ActionContract|array<string, mixed>> $headerActions
     */
    public function headerActions(array $headerActions): static;

    /**
     * @return list<array<string, mixed>>
     */
    public function entrySchema(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
