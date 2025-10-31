<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateModel extends Model
{
    //
    protected $table = 't_template';
    protected $fillable = ['name', 'image'];
}
