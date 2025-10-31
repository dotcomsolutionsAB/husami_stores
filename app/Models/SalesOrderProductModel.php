<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderProductModel extends Model
{
    //
    protected $table = 't_sales_order_products';
    protected $fillable = ['sales_order','product','qty','unit','price','discount','hsn','tax'];
}
