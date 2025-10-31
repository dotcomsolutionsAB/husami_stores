<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaProductModel extends Model
{
    //
    protected $table = 't_proforma_products';
    protected $fillable = ['proforma','product','qty','unit','price','discount','hsn','tax'];
}
