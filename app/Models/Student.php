<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'school_id',
        'name',
        'cpf',
        'enrollment_code',
        'birth_date',
        'grade_level',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot('responsibility_type', 'is_primary')
            ->withTimestamps();
    }
}
