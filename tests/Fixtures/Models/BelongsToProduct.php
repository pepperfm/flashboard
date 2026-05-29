<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

final class BelongsToProduct extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'belongs_to_products';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BelongsToCategory::class, 'category_id');
    }
}
