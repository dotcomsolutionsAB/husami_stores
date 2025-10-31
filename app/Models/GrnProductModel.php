<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrnProductModel extends Model
{
    //
    protected $table = 't_grn_products';
    protected $fillable = ['grn','product','ctn','qty_per_ctn','rack_no'];
}
