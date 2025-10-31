<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccessModel extends Model
{
    //
    protected $table = 't_user_access';
    protected $fillable = [
        'module_name', 'can_create', 'can_view', 'can_edit', 'can_delete'
    ];
}
