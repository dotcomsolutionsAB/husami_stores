<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookOrderModel extends Model
{
    //
    protected $table = 't_book_order';
    protected $fillable = ['supplier','booking_no','booking_date','file'];
}
