<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetCode extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];
}