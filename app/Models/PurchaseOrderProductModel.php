<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderProductModel extends Model
{
    //
    protected $table = 't_purchase_order_products';
    protected $fillable = ['purchase_order','product','qty','unit','price','discount','hsn','tax'];
}
