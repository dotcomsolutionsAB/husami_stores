<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadModel extends Model
{
    //
    protected $table = 't_uploads';
    protected $fillable = ['file_name', 'file_path', 'file_ext', 'file_size'];

    public function brands(): HasMany
    {
        return $this->hasMany(BrandModel::class, 'logo');
    }
}
