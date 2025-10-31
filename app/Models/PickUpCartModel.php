<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickUpCartModel extends Model
{
    //
    protected $table = 't_pick_up_cart';
    protected $fillable = [
        'grade_no','item','size','brand','godown','ctn','total_quantity','cart_no','rack_no'
    ];
}
