<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnrollmentContract extends Model
{
    use HasFactory, SoftDeletes, Auditable;
    protected $fillable = [
    'school_id',
    'student_id',
    'guardian_id',
    'contract_number',
    'academic_year',
    'signed_at',
    'status',
];

protected $casts = [
    'academic_year' => 'integer',
    'signed_at' => 'date',
];

public function school(): BelongsTo
{
    return $this->belongsTo(School::class);
}

public function student(): BelongsTo
{
    return $this->belongsTo(Student::class);
}

public function guardian(): BelongsTo
{
    return $this->belongsTo(Guardian::class);
}

public function debts(): HasMany
{
    return $this->hasMany(Debt::class);
}

}
