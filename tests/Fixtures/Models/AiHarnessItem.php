<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class AiHarnessItem extends Model
{
    protected $table = 'ai_harness_items';

    public $timestamps = false;

    protected $guarded = [];
}
