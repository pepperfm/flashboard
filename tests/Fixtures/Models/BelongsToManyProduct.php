<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

final class BelongsToManyProduct extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'belongs_to_many_products';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            BelongsToManyTag::class,
            'belongs_to_many_product_tag',
            'product_id',
            'tag_id',
            'id',
            'id',
        );
    }
}
