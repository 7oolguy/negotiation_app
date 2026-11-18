<?php

namespace Tests\Unit;

use App\Models\CollectionAction;
use App\Models\Debt;
use App\Models\DebtAssignment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DebtLifecycleService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private DebtLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DebtLifecycleService::class);
    }

    private function createDebt(array $overrides = []): Debt
    {
        $school = School::factory()->create();
        $student = Student::create([
            'school_id' => $school->id,
            'name' => 'Lucas Mendes',
            'enrollment_code' => 'RA-'.fake()->unique()->numerify('#####'),
            'status' => 'active',
        ]);
        $guardian = Guardian::create([
            'school_id' => $school->id,
            'name' => 'Fernanda Mendes',
            'document' => fake()->unique()->numerify('###########'),
            'document_type' => 'cpf',
        ]);

        return Debt::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'external_reference' => 'ERP-'.fake()->unique()->numerify('#####'),
            'principal_cents' => 150000,
            'interest_cents' => 1500,
            'fine_cents' => 3000,
            'correction_cents' => 0,
            'status' => Debt::STATUS_IMPORTED,
            ...$overrides,
        ]);
    }

    public function test_enforces_valid_primary_lifecycle_progression_with_audit_logs(): void
    {
        $actor = User::factory()->create();
        $debt = $this->createDebt();

        $debt = $this->service->transition($debt, Debt::STATUS_PENDING_VERIFICATION, $actor, 'Lote validado');
        $this->assertSame(Debt::STATUS_PENDING_VERIFICATION, $debt->status);

        $debt = $this->service->transition($debt, Debt::STATUS_READY_FOR_COLLECTION, $actor, 'Documentação conferida');
        $this->assertSame(Debt::STATUS_READY_FOR_COLLECTION, $debt->status);

        $debt = $this->service->transition($debt, Debt::STATUS_ACTIVE_COLLECTION, $actor, 'Início de cobrança');
        $this->assertSame(Debt::STATUS_ACTIVE_COLLECTION, $debt->status);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'debt_status_transitioned',
            'auditable_type' => Debt::class,
            'auditable_id' => $debt->id,
        ]);
    }

    public function test_blocks_skipping_lifecycle_steps_and_invalid_transitions(): void
    {
        $debt = $this->createDebt(['status' => Debt::STATUS_IMPORTED]);

        $this->assertFalse($this->service->canTransition($debt, Debt::STATUS_READY_FOR_COLLECTION));
        $this->assertFalse($this->service->canTransition($debt, Debt::STATUS_ACTIVE_COLLECTION));

        try {
            $this->service->transition($debt, Debt::STATUS_ACTIVE_COLLECTION);
            $this->fail('Expected DomainException when jumping from imported to active_collection.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Transição de status não permitida', $e->getMessage());
        }

        $settled = $this->createDebt(['status' => Debt::STATUS_SETTLED]);
        $this->assertSame([], $this->service->allowedNextStatuses($settled));

        $this->expectException(DomainException::class);
        $this->service->transition($settled, Debt::STATUS_ACTIVE_COLLECTION);
    }

    public function test_disputed_and_suspended_holds_pause_collection_and_block_outbound_contacts(): void
    {
        $collector = User::factory()->create();
        $debt = $this->createDebt(['status' => Debt::STATUS_READY_FOR_COLLECTION]);

        // Place dispute hold: cannot enter active_collection.
        $debt = $this->service->placeHold($debt, DebtLifecycleService::HOLD_DISPUTED, 'Responsável contesta valor', $collector);
        $this->assertTrue($debt->is_disputed);
        $this->assertTrue($debt->isCollectionPaused());
        $this->assertFalse($this->service->canTransition($debt, Debt::STATUS_ACTIVE_COLLECTION));

        try {
            $this->service->transition($debt, Debt::STATUS_ACTIVE_COLLECTION, $collector);
            $this->fail('Expected DomainException when activating a disputed debt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('dívida contestada', $e->getMessage());
        }

        // Release dispute -> can activate collection.
        $debt = $this->service->releaseHold($debt, DebtLifecycleService::HOLD_DISPUTED, 'Contestação improcedente', $collector);
        $debt = $this->service->transition($debt, Debt::STATUS_ACTIVE_COLLECTION, $collector);

        // Place suspended hold -> outbound contact blocked, internal note still allowed.
        $debt = $this->service->placeHold($debt, DebtLifecycleService::HOLD_SUSPENDED, 'Pedido da diretoria', $collector);

        try {
            $this->service->logAction($debt, [
                'channel' => CollectionAction::CHANNEL_WHATSAPP,
                'outcome' => 'contacted',
                'notes' => 'Tentativa de cobrança',
            ], $collector);
            $this->fail('Expected DomainException for outbound contact on suspended debt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cobrança suspensa', $e->getMessage());
        }

        // Internal investigation note IS permitted while paused.
        $internalNote = $this->service->logAction($debt, [
            'channel' => CollectionAction::CHANNEL_INTERNAL_NOTE,
            'outcome' => 'note',
            'notes' => 'Aguardando retorno jurídico sobre suspensão.',
        ], $collector);
        $this->assertTrue($internalNote->is_internal);

        // Release suspended hold -> outbound contact works again.
        $debt = $this->service->releaseHold($debt, DebtLifecycleService::HOLD_SUSPENDED, 'Liberado', $collector);
        $action = $this->service->logAction($debt, [
            'channel' => CollectionAction::CHANNEL_PHONE,
            'outcome' => 'contacted',
            'notes' => 'Contato realizado após liberação.',
        ], $collector);
        $this->assertFalse($action->is_internal);
    }

    public function test_logging_disputed_or_reported_paid_outcome_automatically_pauses_collection(): void
    {
        $collector = User::factory()->create();
        $debt = $this->createDebt(['status' => Debt::STATUS_ACTIVE_COLLECTION]);

        $this->service->logAction($debt, [
            'channel' => CollectionAction::CHANNEL_PHONE,
            'outcome' => 'disputed',
            'notes' => 'Responsável afirma já ter cancelado matrícula.',
        ], $collector);

        $debt->refresh();
        $this->assertTrue($debt->is_disputed);
        $this->assertTrue($debt->isCollectionPaused());

        // Subsequent outbound attempt is immediately paused.
        $this->expectException(DomainException::class);
        $this->service->logAction($debt, [
            'channel' => CollectionAction::CHANNEL_WHATSAPP,
            'outcome' => 'message_sent',
            'notes' => 'Nova cobrança indevida',
        ], $collector);
    }

    public function test_outbound_contact_is_blocked_before_debt_reaches_active_collection(): void
    {
        $collector = User::factory()->create();
        $debt = $this->createDebt(['status' => Debt::STATUS_PENDING_VERIFICATION]);

        $this->expectException(DomainException::class);
        $this->service->logAction($debt, [
            'channel' => CollectionAction::CHANNEL_WHATSAPP,
            'outcome' => 'contacted',
        ], $collector);
    }

    public function test_assigning_collector_closes_previous_assignment_and_db_prevents_two_active_assignments(): void
    {
        $manager = User::factory()->create();
        $collectorA = User::factory()->create();
        $collectorB = User::factory()->create();
        $debt = $this->createDebt(['status' => Debt::STATUS_READY_FOR_COLLECTION]);

        $first = $this->service->assignCollector($debt, $collectorA, $manager, 'Primeira atribuição', activateIfReady: true);
        $debt->refresh();
        $this->assertSame(Debt::STATUS_ACTIVE_COLLECTION, $debt->status);
        $this->assertTrue($first->isActive());

        $second = $this->service->assignCollector($debt, $collectorB, $manager, 'Redistribuição');
        $first->refresh();

        $this->assertFalse($first->isActive());
        $this->assertSame('reassigned', $first->end_reason);
        $this->assertTrue($second->isActive());
        $this->assertSame($collectorB->id, $debt->activeAssignment()->value('collector_id'));

        // Direct duplicate active assignment must be rejected by SQLite partial unique index.
        $this->expectException(QueryException::class);
        DebtAssignment::create([
            'debt_id' => $debt->id,
            'collector_id' => $collectorA->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now(),
            'ended_at' => null,
        ]);
    }
}
