<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickUpSlipModel extends Model
{
    //
    protected $table = 't_pick_up_slip';
    protected $fillable = ['client','pick_up_slip_no','status'];

    public function products()
    {
        return $this->hasMany(PickUpSlipProductModel::class, 'pick_up_slip_id', 'id');
    }

    public function clientRef()
    {
        return $this->belongsTo(ClientModel::class, 'client', 'id');
    }
}
