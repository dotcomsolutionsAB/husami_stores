<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaProductModel extends Model
{
    //
    protected $table = 't_proforma_products';
    protected $fillable = ['proforma','sku','qty','unit','price','discount','hsn','tax'];

    public function proformaRef(): BelongsTo
    {
        return $this->belongsTo(ProformaModel::class, 'proforma', 'id');
    }
}
