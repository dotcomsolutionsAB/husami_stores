<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferModel extends Model
{
    //
    protected $table = 't_stock_transfer';
    protected $fillable = ['from_godown','to_godown','transfer_date'];
}
