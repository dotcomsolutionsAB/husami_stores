<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounterModel extends Model
{
    //
    protected $table = 't_counter';
    protected $fillable = ['name', 'prefix', 'number', 'postfix'];

    // so it auto-appears in JSON responses
    protected $appends = ['formatted'];

    public function getFormattedAttribute(): string
    {
        $num = (string)($this->number ?? 0);

        // pad to 4 only if < 4 digits
        if (strlen($num) < 4) {
            $num = str_pad($num, 4, '0', STR_PAD_LEFT);
        }

        return (string)($this->prefix ?? '') . $num . (string)($this->postfix ?? '');
    }
}
