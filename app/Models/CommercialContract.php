<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Parent commercial agreement governing BomTrato's commission and billing terms
 * for a LegalEntity (mantenedora) and/or a specific School.
 */
class CommercialContract extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'legal_entity_id',
        'school_id',
        'reference_code',
        'status',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Mantenedora covered by this contract (when scoped at the legal-entity level).
     */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /**
     * Specific school covered by this contract (when scoped at the school level).
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Immutable rule snapshots created over the lifetime of this contract.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }

    /**
     * Most recent version by version_number (not necessarily effective for a past date).
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(ContractVersion::class)->latestOfMany('version_number');
    }
}
