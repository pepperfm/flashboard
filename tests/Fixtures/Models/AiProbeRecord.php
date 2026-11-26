<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'state', 'occurred_on'])]
final class AiProbeRecord extends Model
{
    protected function casts(): array
    {
        return ['state' => AiProbeState::class, 'occurred_on' => 'date'];
    }
}
