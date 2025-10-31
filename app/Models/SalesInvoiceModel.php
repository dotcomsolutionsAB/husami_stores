<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceModel extends Model
{
    //
    protected $table = 't_sales_invoice';
    protected $fillable = [
        'client','invoice_no','invoice_date','sales_order_no',
        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
    ];
}
