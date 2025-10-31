<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogModel extends Model
{
    //
    protected $table = 't_logs';
    protected $fillable = [
        'item','grade_no','size','brand','godown','quantity','action','user','date'
    ];
}
