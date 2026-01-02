<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GodownModel extends Model
{
    //
    protected $table = 't_godown';
    protected $fillable = ['name'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
