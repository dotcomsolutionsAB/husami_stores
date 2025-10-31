<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationProductModel extends Model
{
    //
    protected $table = 't_quotation_products';
    protected $fillable = ['quotation','product','qty','unit','price','discount','hsn','tax'];
}
