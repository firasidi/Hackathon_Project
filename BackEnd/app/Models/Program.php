<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $guarded = [];
    protected $casts = ['cutoff_score' => 'float'];
}