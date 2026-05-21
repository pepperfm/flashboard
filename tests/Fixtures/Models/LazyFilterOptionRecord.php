<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

final class LazyFilterOptionRecord extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'lazy_filter_option_records';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
