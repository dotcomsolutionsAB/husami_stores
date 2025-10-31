<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientModel extends Model
{
    //
    protected $table = 't_clients';
    protected $fillable = [
        'name','address_line_1','address_line_2','city','pincode','gstin',
        'state','country','mobile','email'
    ];
}
