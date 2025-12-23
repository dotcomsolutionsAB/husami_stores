<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickUpCartModel extends Model
{
    //
    protected $table = 't_pick_up_cart';
    protected $fillable = [
        'godown','ctn', 'sku', 'product_stock_id', 'total_quantity'
    ];
}
