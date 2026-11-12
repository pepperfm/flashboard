<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class AiHarnessCustomer extends Model
{
    protected $table = 'ai_harness_customers';

    public $timestamps = false;

    protected $guarded = [];
}
