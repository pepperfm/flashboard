<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class AiHarnessOrder extends Model
{
    protected $table = 'ai_harness_orders';

    public $timestamps = false;

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(AiHarnessCustomer::class, 'customer_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AiHarnessTag::class, 'ai_harness_order_tag', 'order_id', 'tag_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AiHarnessItem::class, 'order_id');
    }

    public function featuredItem(): HasOne
    {
        return $this->hasOne(AiHarnessItem::class, 'order_id');
    }
}
