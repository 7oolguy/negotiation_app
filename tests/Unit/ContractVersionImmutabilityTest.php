<?php

namespace Tests\Unit;

use App\Models\CommercialContract;
use App\Models\ContractVersion;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\ContractVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class ContractVersionImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private ContractVersioningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ContractVersioningService;
    }

    private function createContract(): CommercialContract
    {
        $legalEntity = LegalEntity::factory()->create();

        return CommercialContract::create([
            'legal_entity_id' => $legalEntity->id,
            'reference_code' => 'CTR-2026-001',
            'status' => 'active',
        ]);
    }

    public function test_contract_version_cannot_be_updated(): void
    {
        $contract = $this->createContract();
        $version = $this->service->createVersion($contract, [
            'rate_type' => 'percentage',
            'rate_bps' => 1500,
        ]);

        try {
            $version->update(['rate_bps' => 2000]);
            $this->fail('Expected LogicException when updating a ContractVersion.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        $this->assertDatabaseHas('contract_versions', [
            'id' => $version->id,
            'rate_bps' => 1500,
        ]);
    }

    public function test_contract_version_cannot_be_deleted(): void
    {
        $contract = $this->createContract();
        $version = $this->service->createVersion($contract, [
            'rate_type' => 'fixed',
            'fixed_fee_cents' => 5000,
        ]);

        try {
            $version->delete();
            $this->fail('Expected LogicException when deleting a ContractVersion.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        $this->assertDatabaseHas('contract_versions', [
            'id' => $version->id,
            'fixed_fee_cents' => 5000,
        ]);
    }

    public function test_creating_new_version_increments_version_number_and_preserves_prior_versions(): void
    {
        $actor = User::factory()->create();
        $contract = $this->createContract();

        $v1 = $this->service->createVersion($contract, [
            'rate_type' => 'percentage',
            'rate_bps' => 1500,
        ], $actor);

        $v2 = $this->service->createVersion($contract, [
            'rate_type' => 'percentage',
            'rate_bps' => 1800,
        ], $actor);

        $this->assertSame(1, $v1->version_number);
        $this->assertSame(2, $v2->version_number);
        $this->assertSame(1500, $v1->fresh()->rate_bps);
        $this->assertSame(1800, $v2->fresh()->rate_bps);
        $this->assertSame($actor->id, $v2->created_by);
        $this->assertTrue($contract->latestVersion->is($v2));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contract_version_created',
            'auditable_type' => ContractVersion::class,
            'auditable_id' => $v1->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contract_version_created',
            'auditable_type' => ContractVersion::class,
            'auditable_id' => $v2->id,
        ]);
    }

    public function test_resolve_effective_version_respects_effective_dates(): void
    {
        $contract = $this->createContract();

        $v1 = $this->service->createVersion($contract, [
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-03-31',
            'rate_bps' => 1200,
        ]);

        $v2 = $this->service->createVersion($contract, [
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'rate_bps' => 1500,
        ]);

        $this->assertTrue(
            $this->service->resolveEffectiveVersion($contract, Carbon::parse('2026-02-15'))?->is($v1) ?? false
        );
        $this->assertTrue(
            $this->service->resolveEffectiveVersion($contract, Carbon::parse('2026-05-01'))?->is($v2) ?? false
        );
        $this->assertNull(
            $this->service->resolveEffectiveVersion($contract, Carbon::parse('2025-12-31'))
        );
    }
}
