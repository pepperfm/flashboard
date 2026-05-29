<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

final class RelationManagerOrder extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RelationManagerProfile::class, 'order_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RelationManagerOrderItem::class, 'order_id');
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BelongsToCategory::class, 'category_id');
    }
}
