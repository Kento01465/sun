<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeRecord extends Model
{
    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];
}
