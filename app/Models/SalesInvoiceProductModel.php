<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceProductModel extends Model
{
    //
    protected $table = 't_sales_invoice_products';
    protected $fillable = ['sales_invoice','product','qty','unit','price','discount','hsn','tax'];
}
