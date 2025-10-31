<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceModel extends Model
{
    //
    protected $table = 't_purchase_invoice';
    protected $fillable = [
        'supplier','purchase_no','purchase_date','oa_no',
        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
    ];
}
