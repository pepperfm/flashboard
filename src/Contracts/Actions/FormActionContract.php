<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Actions;

use Pepperfm\Flashboard\Contracts\Forms\FormContract;

interface FormActionContract extends ContextualActionContract
{
    public function hasForm(): bool;

    /** @param array<string, mixed> $context */
    public function resolveForm(array $context): ?FormContract;
}
