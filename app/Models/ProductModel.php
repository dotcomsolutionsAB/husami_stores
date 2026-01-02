<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    //
    protected $table = 't_products';
    protected $fillable = [
        'sku', 'grade_no','item_name','size','brand','units','list_price','hsn','tax','low_stock_level', 'finish_type', 'specifications'
    ];

    protected $casts = [
        'list_price' => 'decimal:2',
        'tax' => 'decimal:2',
    ];

    public function brandRef()
    {
        // assuming t_products.brand stores t_brand.id
        return $this->belongsTo(BrandModel::class, 'brand', 'id');
    }
}
