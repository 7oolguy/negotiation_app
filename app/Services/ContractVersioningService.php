<?php

namespace App\Services;

use App\Models\CommercialContract;
use App\Models\ContractVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractVersioningService
{
    /**
     * Append a new immutable version to a commercial contract.
     *
     * Never mutates prior versions so historic commission calculations stay
     * tied to the exact contract_version_id that was active at the time.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(
        CommercialContract $contract,
        array $attributes,
        ?User $actor = null,
    ): ContractVersion {
        return DB::transaction(function () use ($contract, $attributes, $actor): ContractVersion {
            // Lock existing versions inside the transaction to prevent duplicate version_number races.
            $latestVersion = $contract->versions()->orderByDesc('version_number')->lockForUpdate()->value('version_number');

            $nextVersion = ($latestVersion ?? 0) + 1;

            // Whitelist rule fields so callers cannot override version_number, created_by, or created_at.
            $allowed = Arr::only($attributes, [
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
            ]);

            $version = $contract->versions()->create([
                ...$allowed,
                'version_number' => $nextVersion,
                'created_by' => $actor?->getKey() ?? Auth::id(),
                'created_at' => now(),
            ]);

            AuditLogger::log(
                event: 'contract_version_created',
                auditable: $version,
                newValues: $version->only([
                    'commercial_contract_id',
                    'version_number',
                    'effective_from',
                    'effective_to',
                ]),
            );

            return $version;
        });
    }

    /**
     * Resolve the version in effect for a contract on a given date.
     *
     * Null effective_from/effective_to bounds are treated as open-ended. When
     * multiple versions overlap, the highest version_number wins.
     */
    public function resolveEffectiveVersion(
        CommercialContract $contract,
        ?CarbonInterface $at = null,
    ): ?ContractVersion {
        $date = ($at ?? now())->toDateString();

        return $contract->versions()
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('version_number')
            ->first();
    }
}
