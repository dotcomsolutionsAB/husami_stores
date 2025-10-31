<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockModel extends Model
{
    //
    protected $table = 't_product_stocks';
    protected $fillable = []; // Extend later if you add fields like product_id, qty, etc.
}
