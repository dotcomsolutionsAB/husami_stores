<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickUpSlipProductModel extends Model
{
    //
    protected $table = 't_pick_up_slip_products';
    protected $fillable = ['pick_up_slip','product','warehouse','rack_no','qty','remarks'];
}
