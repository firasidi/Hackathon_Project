<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $guarded = [];
    protected $casts = ['score' => 'float'];

    public function application()
    {
        return $this->hasOne(Application::class);
    }
}