<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickUpSlipProductModel extends Model
{
    //
    protected $table = 't_pick_up_slip_products';
    protected $fillable = ['pick_up_slip_id','product_stock_id', 'sku', 'godown','ctn','quantity','approved','remarks'];

    public function slip()
    {
        return $this->belongsTo(PickUpSlipModel::class, 'pick_up_slip_id', 'id');
    }

    public function stock()
    {
        return $this->belongsTo(ProductStockModel::class, 'product_stock_id', 'id');
    }
}
