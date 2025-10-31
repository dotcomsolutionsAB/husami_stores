<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandModel extends Model
{
    //
    protected $table = 't_brand';
    protected $fillable = ['name', 'order_by', 'hex_code', 'logo'];

    public function logoRef(): BelongsTo
    {
        return $this->belongsTo(UploadModel::class, 'logo');
    }
}
