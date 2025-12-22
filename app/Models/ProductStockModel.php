<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockModel extends Model
{
    //
    protected $table = 't_product_stocks';
    // protected $fillable = []; // Extend later if you add fields like product_id, qty, etc.
    protected $fillable = ['product_id', 'godown_id', 'quantity', 'ctn', 'batch_no', 'rack_no', 'invoice_no', 'invoice_date', 'tc_no', 'tc_date', 'tc_attachment', 'remarks']; // Extend later if you add fields like product_id, qty, etc.
}
