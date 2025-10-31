<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickUpSlipModel extends Model
{
    //
    protected $table = 't_pick_up_slip';
    protected $fillable = ['client','pick_up_slip_no'];
}
