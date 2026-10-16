<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingPreferenceSubmission extends Model
{
    protected $guarded = [];

    protected $casts = [
        'choices' => 'array',
        'expires_at' => 'datetime',
    ];
}
