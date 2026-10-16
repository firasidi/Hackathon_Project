<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = [];
    protected $hidden = ['password'];
    protected $casts = [
        'score' => 'float',
        'is_admin' => 'boolean',
    ];

    public function application()
    {
        return $this->hasOne(Application::class);
    }
}