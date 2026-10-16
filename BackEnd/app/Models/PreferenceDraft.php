<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreferenceDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'choices' => 'array',
    ];
}
