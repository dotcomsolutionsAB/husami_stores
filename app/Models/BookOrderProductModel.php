<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookOrderProductModel extends Model
{
    //
    protected $table = 't_book_order_products';
    protected $fillable = ['book_order','product','qty'];
}
