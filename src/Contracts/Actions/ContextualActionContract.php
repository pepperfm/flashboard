<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Contracts\Actions;

interface ContextualActionContract extends ActionContract
{
    public function actionScope(): ActionScope;

    public function requiredAbility(): ?string;

    /** @param array<string, mixed> $context */
    public function isVisible(array $context): bool;

    /** @param array<string, mixed> $context */
    public function isAuthorized(array $context): bool;
}
