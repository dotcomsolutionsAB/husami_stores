<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaModel extends Model
{
    //
    protected $table = 't_proforma';
    protected $fillable = [
        'client','proforma_no','proforma_date','quotation','sales_order_no',
        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
    ];
}
