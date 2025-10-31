<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    //
    protected $table = 't_products';
    protected $fillable = [
        'grade_no','item_name','size','brand','units','list_price','hsn','tax','low_stock_level'
    ];
}
