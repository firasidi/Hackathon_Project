<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    protected $guarded = [];

    protected $casts = [
        'minimum_score' => 'float',
        'cutoff_score' => 'float',
    ];

    public function specialty()
    {
        return $this->belongsTo(Program::class, 'specialty_id');
    }
}
