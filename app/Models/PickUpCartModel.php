<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickUpCartModel extends Model
{
    //
    protected $table = 't_pick_up_cart';
    protected $fillable = [
        'user_id','godown','ctn', 'sku', 'product_stock_id', 'quantity'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
