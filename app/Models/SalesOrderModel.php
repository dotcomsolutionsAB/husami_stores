<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderModel extends Model
{
    //
    protected $table = 't_sales_order';
    protected $fillable = [
        'client','sales_order_no','sales_order_date','quotation','client_order_no',
        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
    ];

    public function products(): HasMany
    {
        return $this->hasMany(SalesOrderProductModel::class, 'sales_order', 'id');
    }
}
