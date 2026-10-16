<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded = [];
    protected $casts = [
        'choices' => 'array',
        'receipt_choices' => 'array',
        'receipt_submitted_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'placed_program_id');
    }

    public function university()
    {
        return $this->belongsTo(University::class, 'placed_university_id');
    }
}