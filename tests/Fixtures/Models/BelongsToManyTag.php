<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

final class BelongsToManyTag extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'belongs_to_many_tags';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];
}
