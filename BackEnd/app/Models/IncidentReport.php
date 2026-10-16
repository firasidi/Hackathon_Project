<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'report_data' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
