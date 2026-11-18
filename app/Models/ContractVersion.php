<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Override;

/**
 * Immutable snapshot of commercial contract rules (rates, attribution window,
 * PSP fee treatment, billing cycle, and approval limits).
 *
 * All money values are stored in integer centavos and rates in basis points
 * (100 bps = 1%) to avoid floating-point rounding errors.
 */
class ContractVersion extends Model
{
    use HasFactory;

    // Append-only table: no updated_at column exists.
    public $timestamps = false;

    protected $fillable = [
        'commercial_contract_id',
        'version_number',
        'effective_from',
        'effective_to',
        'rate_type',
        'rate_bps',
        'fixed_fee_cents',
        'rate_tiers',
        'attribution_window_days',
        'eligible_components',
        'psp_fee_treatment',
        'billing_closing_day',
        'approval_rules',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'rate_bps' => 'integer',
            'fixed_fee_cents' => 'integer',
            'rate_tiers' => 'array',
            'attribution_window_days' => 'integer',
            'eligible_components' => 'array',
            'billing_closing_day' => 'integer',
            'approval_rules' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Block Eloquent updates and deletes so historic commission calculations
     * always reference an unaltered rule snapshot.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Contract versions are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Contract versions are immutable and cannot be deleted.');
        });
    }

    public function commercialContract(): BelongsTo
    {
        return $this->belongsTo(CommercialContract::class);
    }

    /**
     * User who authored this contract version.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
