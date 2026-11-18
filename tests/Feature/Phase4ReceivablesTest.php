<?php

namespace Tests\Feature;

use App\Filament\Resources\Guardians\Pages\CreateGuardian;
use App\Models\Debt;
use App\Models\Guardian;
use App\Models\ImportJob;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DebtImportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4ReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $permissions = [
            'ViewAny:Guardian', 'View:Guardian', 'Create:Guardian', 'Update:Guardian',
            'ViewAny:Student', 'View:Student', 'Create:Student', 'Update:Student',
            'ViewAny:Debt', 'View:Debt', 'Create:Debt', 'Update:Debt',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role->syncPermissions($permissions);
        $this->admin->assignRole($role);

        $this->actingAs($this->admin);
    }

    public function test_duplicate_guardian_document_is_prevented_within_same_school_but_allowed_across_schools(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        Guardian::create([
            'school_id' => $schoolA->id,
            'name' => 'João Silva',
            'document' => '12345678909',
            'document_type' => 'cpf',
        ]);

        // Same document in another school is allowed.
        $otherSchoolGuardian = Guardian::create([
            'school_id' => $schoolB->id,
            'name' => 'João Silva',
            'document' => '12345678909',
            'document_type' => 'cpf',
        ]);
        $this->assertDatabaseHas('guardians', ['id' => $otherSchoolGuardian->id]);

        // Filament form rejects duplicate document in the same school.
        Livewire::test(CreateGuardian::class)
            ->fillForm([
                'school_id' => $schoolA->id,
                'name' => 'João Duplicado',
                'document_type' => 'cpf',
                'document' => '123.456.789-09',
            ])
            ->call('create')
            ->assertHasFormErrors(['document']);

        // Database constraint also enforces uniqueness per school.
        $this->expectException(QueryException::class);
        Guardian::create([
            'school_id' => $schoolA->id,
            'name' => 'João Duplicado DB',
            'document' => '12345678909',
            'document_type' => 'cpf',
        ]);
    }

    public function test_one_guardian_can_be_mapped_to_multiple_students(): void
    {
        $school = School::factory()->create();

        $student1 = Student::create([
            'school_id' => $school->id,
            'name' => 'Ana Souza',
            'enrollment_code' => 'RA-001',
            'status' => 'active',
        ]);
        $student2 = Student::create([
            'school_id' => $school->id,
            'name' => 'Pedro Souza',
            'enrollment_code' => 'RA-002',
            'status' => 'active',
        ]);

        Livewire::test(CreateGuardian::class)
            ->fillForm([
                'school_id' => $school->id,
                'name' => 'Carlos Souza',
                'document_type' => 'cpf',
                'document' => '111.222.333-44',
                'students' => [$student1->id, $student2->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $guardian = Guardian::where('document', '11122233344')->firstOrFail();

        $this->assertCount(2, $guardian->students);
        $this->assertTrue($guardian->students->contains($student1));
        $this->assertTrue($guardian->students->contains($student2));
        $this->assertSame('financial', $guardian->students->first()->pivot->responsibility_type);
    }

    public function test_debt_import_service_deduplicates_guardians_and_debts_and_logs_import_job(): void
    {
        $school = School::factory()->create();
        $service = app(DebtImportService::class);

        $csv = <<<'CSV'
guardian_name;guardian_document;guardian_email;student_name;enrollment_code;external_reference;principal_cents;interest_cents;fine_cents;due_date
Maria Oliveira;529.982.247-25;maria@example.com;Lucas Oliveira;RA-100;ERP-001;150000;1500;3000;2026-09-10
Maria Oliveira;529.982.247-25;maria@example.com;Sofia Oliveira;RA-101;ERP-002;120000;0;0;2026-09-10
Invalid Row;123;bad@example.com;Nobody;RA-999;ERP-999;not-a-number;0;0;2026-09-10
CSV;

        $firstJob = $service->import($school, $csv, 'september.csv', 'imports/debts/september.csv', $this->admin);

        $this->assertSame(3, $firstJob->total_rows);
        $this->assertSame(2, $firstJob->imported_rows);
        $this->assertSame(0, $firstJob->skipped_rows);
        $this->assertSame(1, $firstJob->failed_rows);
        $this->assertCount(1, $firstJob->errors);

        // Single guardian shared by both students (no duplicate guardian created).
        $this->assertSame(1, Guardian::where('school_id', $school->id)->count());
        $guardian = Guardian::where('school_id', $school->id)->firstOrFail();
        $this->assertCount(2, $guardian->students);
        $this->assertSame(2, Debt::where('school_id', $school->id)->count());

        $debt = Debt::where('external_reference', 'ERP-001')->firstOrFail();
        $this->assertSame(150000, $debt->principal_cents);
        $this->assertCount(1, $debt->installments);

        // Re-importing the same valid rows skips them as duplicates.
        $reimportCsv = <<<'CSV'
guardian_name,guardian_document,student_name,enrollment_code,external_reference,principal_cents,due_date
Maria Oliveira,52998224725,Lucas Oliveira,RA-100,ERP-001,150000,2026-09-10
CSV;

        $secondJob = $service->import($school, $reimportCsv, 'reimport.csv', null, $this->admin);

        $this->assertSame(1, $secondJob->total_rows);
        $this->assertSame(0, $secondJob->imported_rows);
        $this->assertSame(1, $secondJob->skipped_rows);
        $this->assertSame(0, $secondJob->failed_rows);
        $this->assertSame('duplicate', $secondJob->errors[0]['type']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'debt_import_completed',
            'auditable_type' => ImportJob::class,
            'auditable_id' => $firstJob->id,
        ]);
    }
}
