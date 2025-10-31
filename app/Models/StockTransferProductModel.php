<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferProductModel extends Model
{
    //
    protected $table = 't_stock_transfer_products';
    protected $fillable = ['stock_transfer','product','quantity','description'];
}
