<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;

final class RelationSelectionPolicy
{
    public function viewAny(GenericUser $user): bool
    {
        return $user->getAuthIdentifier() === 1;
    }

    public function view(GenericUser $user, Model $record): bool
    {
        return $this->viewAny($user) && $record->getAttribute('slug') !== 'policy-denied';
    }
}
