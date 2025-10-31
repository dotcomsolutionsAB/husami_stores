<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderModel extends Model
{
    //
    protected $table = 't_purchase_order';
    protected $fillable = [
        'supplier','purchase_order_no','purchase_order_date','oa_no','booking_no',
        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
    ];
}
