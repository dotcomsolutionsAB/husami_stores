<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationProductModel extends Model
{
    //
    protected $table = 't_quotation_products';
    protected $fillable = ['quotation','sku','qty','unit','price','discount','hsn','tax'];

    public function quotationRef()
    {
        return $this->belongsTo(QuotationModel::class, 'quotation', 'id');
    }

    public function productRef()
    {
        // ✅ map quotation_products.sku -> t_products.sku
        return $this->belongsTo(ProductModel::class, 'sku', 'sku');
    }
}
