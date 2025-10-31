<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceProductModel extends Model
{
    //
    protected $table = 't_purchase_invoice_products';
    protected $fillable = ['purchase_invoice','product','qty','unit','price','discount','hsn','tax'];
}
