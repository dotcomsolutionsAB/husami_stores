<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderProductModel extends Model
{
    //
    protected $table = 't_sales_order_products';
    protected $fillable = ['sales_order','sku','qty','unit','price','discount','hsn','tax'];

    public function productRef(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'sku', 'sku'); // sku is string
    }
}
