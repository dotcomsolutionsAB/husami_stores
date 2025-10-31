<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierModel extends Model
{
    //
    protected $table = 't_suppliers';
    protected $fillable = [
        'name','address_line_1','address_line_2','city','pincode','gstin',
        'state','country','mobile','email'
    ];
}
