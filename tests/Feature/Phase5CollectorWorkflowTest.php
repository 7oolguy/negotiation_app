<?php

namespace Tests\Feature;

use App\Filament\Pages\CollectorWorkspace;
use App\Filament\Resources\Debts\Pages\ViewDebt;
use App\Filament\Resources\Debts\RelationManagers\CollectionActionsRelationManager;
use App\Filament\Widgets\TodaysCollectionTasksWidget;
use App\Models\AuditLog;
use App\Models\CollectionAction;
use App\Models\CollectionTask;
use App\Models\Debt;
use App\Models\DebtAssignment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DebtLifecycleService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase5CollectorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createDebtForSchool(School $school, array $overrides = []): Debt
    {
        $student = Student::create([
            'school_id' => $school->id,
            'name' => 'Aluno '.fake()->firstName(),
            'enrollment_code' => 'RA-'.fake()->unique()->numerify('#####'),
            'status' => 'active',
        ]);

        $guardian = Guardian::create([
            'school_id' => $school->id,
            'name' => 'Responsável '.fake()->lastName(),
            'document' => fake()->unique()->numerify('###########'),
            'document_type' => 'cpf',
        ]);

        return Debt::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'external_reference' => 'ERP-'.fake()->unique()->numerify('#####'),
            'principal_cents' => 200000,
            'interest_cents' => 5000,
            'fine_cents' => 4000,
            'correction_cents' => 0,
            'status' => Debt::STATUS_ACTIVE_COLLECTION,
            ...$overrides,
        ]);
    }

    private function createUserWithRole(string $roleName, array $permissions = [], ?School $school = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        if ($permissions !== []) {
            $role->givePermissionTo($permissions);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        if ($school !== null) {
            $user->schools()->attach($school);
        }

        return $user;
    }

    public function test_internal_notes_are_flagged_and_excluded_from_school_facing_queries_and_policies(): void
    {
        $schoolA = School::factory()->create(['name' => 'Escola A']);
        $debtA = $this->createDebtForSchool($schoolA);

        $collector = $this->createUserWithRole('collector', ['ViewAny:Debt', 'View:Debt']);
        $schoolViewerA = $this->createUserWithRole(
            'school_viewer',
            ['ViewAny:Debt', 'View:Debt', 'Create:Debt', 'Update:Debt', 'Delete:Debt'],
            $schoolA,
        );

        $service = app(DebtLifecycleService::class);

        $publicAction = $service->logAction($debtA, [
            'channel' => CollectionAction::CHANNEL_WHATSAPP,
            'outcome' => 'contacted',
            'notes' => 'Mensagem enviada ao responsável.',
            'is_internal' => false,
        ], $collector);

        $flaggedInternal = $service->logAction($debtA, [
            'channel' => CollectionAction::CHANNEL_PHONE,
            'outcome' => 'note',
            'notes' => 'Avaliação jurídica confidencial.',
            'is_internal' => true,
        ], $collector);

        // channel=internal_note must automatically force is_internal=true even if caller passes false.
        $autoInternal = $service->logAction($debtA, [
            'channel' => CollectionAction::CHANNEL_INTERNAL_NOTE,
            'outcome' => 'note',
            'notes' => 'Nota interna automática.',
            'is_internal' => false,
        ], $collector);

        $this->assertTrue($autoInternal->is_internal);

        // School-facing queries strictly exclude internal notes.
        $visibleIdsForSchool = CollectionAction::forUser($schoolViewerA)->pluck('id')->all();
        $this->assertSame([$publicAction->id], $visibleIdsForSchool);
        $this->assertSame([$publicAction->id], $debtA->publicCollectionActions()->pluck('id')->all());

        // Internal collector sees all 3 actions.
        $this->assertCount(3, CollectionAction::forUser($collector)->get());

        // Policy defense-in-depth: school_viewer can view public action, never internal actions, and is read-only.
        $this->assertTrue($schoolViewerA->can('view', $publicAction));
        $this->assertFalse($schoolViewerA->can('view', $flaggedInternal));
        $this->assertFalse($schoolViewerA->can('view', $autoInternal));
        $this->assertFalse($schoolViewerA->can('create', CollectionAction::class));
        $this->assertFalse($schoolViewerA->can('update', $publicAction));
        $this->assertFalse($schoolViewerA->can('delete', $publicAction));
        $this->assertFalse($schoolViewerA->can('create', Debt::class));
        $this->assertFalse($schoolViewerA->can('update', $debtA));
        $this->assertFalse($schoolViewerA->can('create', DebtAssignment::class));
        $this->assertFalse($schoolViewerA->can('create', CollectionTask::class));
    }

    public function test_school_viewer_timeline_and_private_evidence_enforce_school_isolation_and_internal_exclusion(): void
    {
        Storage::fake('private');

        $schoolA = School::factory()->create(['name' => 'Escola A']);
        $schoolB = School::factory()->create(['name' => 'Escola B']);

        $debtA = $this->createDebtForSchool($schoolA);
        $debtB = $this->createDebtForSchool($schoolB);

        $collector = $this->createUserWithRole('collector', ['ViewAny:Debt', 'View:Debt']);
        $schoolViewerA = $this->createUserWithRole('school_viewer', ['ViewAny:Debt', 'View:Debt'], $schoolA);

        Storage::disk('private')->put('collection-evidence/public-proof.pdf', 'public-proof-bytes');
        Storage::disk('private')->put('collection-evidence/internal-memo.pdf', 'internal-memo-bytes');
        Storage::disk('private')->put('collection-evidence/school-b-proof.pdf', 'school-b-bytes');

        $service = app(DebtLifecycleService::class);

        $publicActionA = $service->logAction($debtA, [
            'channel' => CollectionAction::CHANNEL_WHATSAPP,
            'outcome' => 'message_sent',
            'notes' => 'Comprovante de envio público.',
            'evidence_path' => 'collection-evidence/public-proof.pdf',
            'evidence_original_name' => 'comprovante.pdf',
        ], $collector);

        $internalActionA = $service->logAction($debtA, [
            'channel' => CollectionAction::CHANNEL_INTERNAL_NOTE,
            'outcome' => 'note',
            'notes' => 'Estratégia interna confidencial.',
            'evidence_path' => 'collection-evidence/internal-memo.pdf',
            'evidence_original_name' => 'interno.pdf',
        ], $collector);

        $publicActionB = $service->logAction($debtB, [
            'channel' => CollectionAction::CHANNEL_WHATSAPP,
            'outcome' => 'message_sent',
            'notes' => 'Contato da Escola B.',
            'evidence_path' => 'collection-evidence/school-b-proof.pdf',
            'evidence_original_name' => 'escola-b.pdf',
        ], $collector);

        // Unauthenticated requests to private evidence are redirected.
        $this->get(route('collection-actions.evidence', $publicActionA))->assertRedirect();

        // Acting as School A viewer:
        $this->actingAs($schoolViewerA);

        // Can view School A debt and sees ONLY public action in timeline relation manager.
        Livewire::test(ViewDebt::class, ['record' => $debtA->getRouteKey()])
            ->assertOk();

        Livewire::test(CollectionActionsRelationManager::class, [
            'ownerRecord' => $debtA,
            'pageClass' => ViewDebt::class,
        ])
            ->assertCanSeeTableRecords([$publicActionA])
            ->assertCanNotSeeTableRecords([$internalActionA]);

        // Cannot forge School B debt URL.
        $this->get(ViewDebt::getUrl(['record' => $debtB]))->assertForbidden();

        // Evidence downloads: allowed for School A public, forbidden for School A internal & School B.
        $this->get(route('collection-actions.evidence', $publicActionA))->assertOk();
        $this->get(route('collection-actions.evidence', $internalActionA))->assertForbidden();
        $this->get(route('collection-actions.evidence', $publicActionB))->assertForbidden();

        // Authorized download is recorded in audit_logs (user, action, debt_id, IP); denied downloads are not.
        $downloadLog = AuditLog::query()
            ->where('event', 'collection_evidence_downloaded')
            ->where('auditable_type', CollectionAction::class)
            ->where('auditable_id', $publicActionA->id)
            ->first();

        $this->assertNotNull($downloadLog);
        $this->assertSame($schoolViewerA->id, $downloadLog->user_id);
        $this->assertSame($debtA->id, $downloadLog->metadata['debt_id'] ?? null);
        $this->assertNotEmpty($downloadLog->metadata['ip'] ?? null);

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'collection_evidence_downloaded',
            'auditable_type' => CollectionAction::class,
            'auditable_id' => $internalActionA->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'collection_evidence_downloaded',
            'auditable_type' => CollectionAction::class,
            'auditable_id' => $publicActionB->id,
        ]);

        // School viewer cannot access Collector Workspace or Today's Tasks widget.
        $this->assertFalse(CollectorWorkspace::canAccess());
        $this->assertFalse(TodaysCollectionTasksWidget::canView());
        $this->get(CollectorWorkspace::getUrl())->assertForbidden();
    }

    public function test_collector_workspace_portfolio_todays_tasks_and_quick_log_respect_disputed_pause(): void
    {
        $school = School::factory()->create();
        $debtActive = $this->createDebtForSchool($school, ['status' => Debt::STATUS_ACTIVE_COLLECTION]);
        $debtDisputed = $this->createDebtForSchool($school, [
            'status' => Debt::STATUS_ACTIVE_COLLECTION,
            'is_disputed' => true,
        ]);
        $debtOtherCollector = $this->createDebtForSchool($school, ['status' => Debt::STATUS_ACTIVE_COLLECTION]);

        $manager = $this->createUserWithRole('manager', ['ViewAny:Debt', 'View:Debt', 'Update:Debt']);
        $collector1 = $this->createUserWithRole('collector', ['ViewAny:Debt', 'View:Debt']);
        $collector2 = $this->createUserWithRole('collector', ['ViewAny:Debt', 'View:Debt']);

        $service = app(DebtLifecycleService::class);
        $assignment1 = $service->assignCollector($debtActive, $collector1, $manager);
        $service->assignCollector($debtDisputed, $collector1, $manager);
        $service->assignCollector($debtOtherCollector, $collector2, $manager);

        $todayTask = $service->scheduleTask($debtActive, $collector1, 'Ligar hoje para confirmar boleto', now()->startOfDay()->addHours(10));
        $futureTask = $service->scheduleTask($debtActive, $collector1, 'Retorno semana que vem', now()->addDays(5));
        $otherCollectorTask = $service->scheduleTask($debtOtherCollector, $collector2, 'Tarefa de outro cobrador', now()->startOfDay()->addHours(10));

        $this->actingAs($collector1);

        // Collector 1 sees only their assigned debts on Collector Workspace.
        Livewire::test(CollectorWorkspace::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$debtActive, $debtDisputed])
            ->assertCanNotSeeTableRecords([$debtOtherCollector])
            ->callAction(TestAction::make('quickLog')->table($debtActive), data: [
                'channel' => CollectionAction::CHANNEL_WHATSAPP,
                'outcome' => 'promise_to_pay',
                'is_internal' => false,
                'notes' => 'Prometeu pagamento até sexta.',
                'schedule_followup' => true,
                'followup_title' => 'Confirmar pagamento na sexta',
                'followup_due_at' => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertNotified('Ação registrada com sucesso');

        $this->assertDatabaseHas('collection_actions', [
            'debt_id' => $debtActive->id,
            'debt_assignment_id' => $assignment1->id,
            'user_id' => $collector1->id,
            'outcome' => 'promise_to_pay',
            'is_internal' => false,
        ]);
        $this->assertDatabaseHas('collection_tasks', [
            'debt_id' => $debtActive->id,
            'assigned_to' => $collector1->id,
            'title' => 'Confirmar pagamento na sexta',
            'status' => CollectionTask::STATUS_PENDING,
        ]);

        // Quick-log outbound contact on disputed debt is blocked and creates no action.
        Livewire::test(CollectorWorkspace::class)
            ->callAction(TestAction::make('quickLog')->table($debtDisputed), data: [
                'channel' => CollectionAction::CHANNEL_PHONE,
                'outcome' => 'contacted',
                'is_internal' => false,
                'notes' => 'Não deveria registrar',
            ])
            ->assertNotified('Ação bloqueada');

        $this->assertDatabaseMissing('collection_actions', [
            'debt_id' => $debtDisputed->id,
            'notes' => 'Não deveria registrar',
        ]);

        // Today's Tasks widget shows only Collector 1's due-today task and lets them complete it.
        Livewire::test(TodaysCollectionTasksWidget::class)
            ->assertCanSeeTableRecords([$todayTask])
            ->assertCanNotSeeTableRecords([$futureTask, $otherCollectorTask])
            ->callAction(TestAction::make('complete')->table($todayTask))
            ->assertNotified('Tarefa concluída');

        $todayTask->refresh();
        $this->assertSame(CollectionTask::STATUS_COMPLETED, $todayTask->status);
        $this->assertSame($collector1->id, $todayTask->completed_by);
    }

    public function test_view_debt_header_actions_transition_status_and_assign_collector(): void
    {
        $school = School::factory()->create();
        $debt = $this->createDebtForSchool($school, ['status' => Debt::STATUS_IMPORTED]);

        $admin = $this->createUserWithRole('super_admin', ['ViewAny:Debt', 'View:Debt', 'Update:Debt']);
        $collector = $this->createUserWithRole('collector', ['ViewAny:Debt', 'View:Debt']);

        $this->actingAs($admin);

        // Smoke-check Dashboard (with TodaysCollectionTasksWidget) and Collector Workspace Livewire pages.
        Livewire::test(\Filament\Pages\Dashboard::class)->assertOk();
        Livewire::test(CollectorWorkspace::class)->assertOk();

        Livewire::test(ViewDebt::class, ['record' => $debt->getRouteKey()])
            ->assertOk()
            ->callAction('transitionStatus', data: [
                'target_status' => Debt::STATUS_PENDING_VERIFICATION,
                'reason' => 'Enviado para conferência documental',
            ])
            ->assertNotified('Status atualizado')
            ->callAction('assignCollector', data: [
                'collector_id' => $collector->id,
                'notes' => 'Carteira inicial',
            ])
            ->assertNotified('Cobrador atribuído');

        $debt->refresh();
        $this->assertSame(Debt::STATUS_PENDING_VERIFICATION, $debt->status);
        $this->assertSame($collector->id, $debt->activeAssignment()->value('collector_id'));
    }
}
