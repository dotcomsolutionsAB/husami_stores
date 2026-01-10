<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationModel extends Model
{
    //
    protected $table = 't_quotation';
    protected $fillable = [
        'client','quotation','quotation_date','enquiry','enquiry_date','template',
        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
    ];

    public function products()
    {
        return $this->hasMany(QuotationProductModel::class, 'quotation', 'id');
    }
}
