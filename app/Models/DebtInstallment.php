<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtInstallment extends Model
{
    use HasFactory, Auditable;
    protected $fillable = [
    'debt_id',
    'installment_number',
    'external_reference',
    'due_date',
    'principal_cents',
    'interest_cents',
    'fine_cents',
    'correction_cents',
    'paid_cents',
    'status',
];

protected $casts = [
    'installment_number' => 'integer',
    'due_date' => 'date',
    'principal_cents' => 'integer',
    'interest_cents' => 'integer',
    'fine_cents' => 'integer',
    'correction_cents' => 'integer',
    'paid_cents' => 'integer',
];

public function debt(): BelongsTo
{
    return $this->belongsTo(Debt::class);
}

}
