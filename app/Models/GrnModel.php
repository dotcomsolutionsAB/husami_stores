<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrnModel extends Model
{
    //
    protected $table = 't_grn';
    protected $fillable = ['purchase_order','grn','date','godown','file'];
}
