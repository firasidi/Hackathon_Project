<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $guarded = [];
    protected $casts = [
        'cutoff_score' => 'float',
        'minimum_score' => 'float',
    ];

    public function universities()
    {
        return $this->hasMany(University::class, 'specialty_id');
    }
}