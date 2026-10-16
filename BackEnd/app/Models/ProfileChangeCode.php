<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileChangeCode extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
