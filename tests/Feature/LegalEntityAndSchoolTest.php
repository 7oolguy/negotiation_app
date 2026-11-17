<?php

namespace Tests\Feature;

use App\Filament\Resources\LegalEntities\Pages\CreateLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\EditLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\ListLegalEntities;
use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Filament\Resources\Schools\Pages\EditSchool;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Filament\Resources\Schools\Pages\ViewSchool;
use App\Models\LegalEntity;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegalEntityAndSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $permissions = [
            'ViewAny:LegalEntity',
            'View:LegalEntity',
            'Create:LegalEntity',
            'Update:LegalEntity',
            'ViewAny:School',
            'View:School',
            'Create:School',
            'Update:School',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role->syncPermissions($permissions);
        $user->assignRole($role);

        $this->actingAs($user);
    }

    public function test_a_school_belongs_to_one_legal_entity_and_a_legal_entity_can_have_multiple_schools(): void
    {
        $legalEntity = LegalEntity::factory()->create();
        $schools = School::factory()->count(3)->for($legalEntity)->create();

        $this->assertCount(3, $legalEntity->fresh()->schools);

        foreach ($schools as $school) {
            $this->assertTrue($school->legalEntity->is($legalEntity));
        }
    }

    public function test_school_cannot_be_created_without_a_legal_entity(): void
    {
        $this->expectException(QueryException::class);

        School::create([
            'name' => 'Orphan School',
            'legal_name' => 'Orphan School Ltda',
            'cnpj' => '12345678000199',
            'email' => 'orphan@example.com',
        ]);
    }

    public function test_school_cannot_reference_a_non_existent_legal_entity(): void
    {
        $this->expectException(QueryException::class);

        School::create([
            'legal_entity_id' => 999999,
            'name' => 'Invalid FK School',
            'legal_name' => 'Invalid FK School Ltda',
            'cnpj' => '12345678000198',
            'email' => 'invalid-fk@example.com',
        ]);
    }

    public function test_force_deleting_a_legal_entity_cascades_to_its_schools(): void
    {
        $legalEntity = LegalEntity::factory()->create();
        $schools = School::factory()->count(2)->for($legalEntity)->create();

        $legalEntity->forceDelete();

        $this->assertDatabaseMissing('legal_entities', ['id' => $legalEntity->id]);

        foreach ($schools as $school) {
            $this->assertDatabaseMissing('schools', ['id' => $school->id]);
        }
    }

    public function test_soft_deleting_a_legal_entity_keeps_its_schools(): void
    {
        $legalEntity = LegalEntity::factory()->create();
        $school = School::factory()->for($legalEntity)->create();

        $legalEntity->delete();

        $this->assertSoftDeleted('legal_entities', ['id' => $legalEntity->id]);
        $this->assertNotSoftDeleted('schools', ['id' => $school->id]);
    }

    public function test_can_list_create_and_edit_legal_entities_in_filament(): void
    {
        $existing = LegalEntity::factory()->create();

        Livewire::test(ListLegalEntities::class)
            ->assertCanSeeTableRecords([$existing]);

        Livewire::test(CreateLegalEntity::class)
            ->fillForm([
                'legal_name' => 'Mantenedora Alpha Ltda',
                'trade_name' => 'Grupo Alpha',
                'cnpj' => '11222333000181',
                'email' => 'contato@alpha.edu.br',
                'phone' => '(11) 99999-8888',
                'address' => 'Rua das Flores, 100',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'zip_code' => '01310100',
                'country' => 'Brasil',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = LegalEntity::where('cnpj', '11222333000181')->firstOrFail();

        Livewire::test(EditLegalEntity::class, ['record' => $created->getRouteKey()])
            ->fillForm([
                'trade_name' => 'Grupo Alpha Atualizado',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Grupo Alpha Atualizado', $created->fresh()->trade_name);
    }

    public function test_school_form_requires_legal_entity_and_supports_crud_in_filament(): void
    {
        $legalEntityA = LegalEntity::factory()->create();
        $legalEntityB = LegalEntity::factory()->create();
        $existingSchool = School::factory()->for($legalEntityA)->create();

        Livewire::test(ListSchools::class)
            ->assertCanSeeTableRecords([$existingSchool]);

        Livewire::test(CreateSchool::class)
            ->fillForm([
                'name' => 'Escola Sem Mantenedora',
                'legal_name' => 'Escola Sem Mantenedora Ltda',
                'cnpj' => '22333444000172',
                'email' => 'sem@escola.edu.br',
                'phone' => '(11) 98888-7777',
                'address' => 'Av. Paulista, 500',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'zip_code' => '01310200',
                'country' => 'Brasil',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['legal_entity_id' => 'required']);

        Livewire::test(CreateSchool::class)
            ->fillForm([
                'legal_entity_id' => $legalEntityA->id,
                'name' => 'Colegio BomTrato',
                'legal_name' => 'Colegio BomTrato Ltda',
                'cnpj' => '33444555000163',
                'email' => 'secretaria@bomtrato.edu.br',
                'phone' => '(11) 97777-6666',
                'address' => 'Rua Augusta, 200',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'zip_code' => '01305000',
                'country' => 'Brasil',
                'website' => 'https://bomtrato.edu.br',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = School::where('cnpj', '33444555000163')->firstOrFail();
        $this->assertTrue($created->legalEntity->is($legalEntityA));

        Livewire::test(ViewSchool::class, ['record' => $created->getRouteKey()])
            ->assertOk();

        Livewire::test(EditSchool::class, ['record' => $created->getRouteKey()])
            ->fillForm([
                'legal_entity_id' => $legalEntityB->id,
                'name' => 'Colegio BomTrato Unidade II',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $created->refresh();
        $this->assertSame('Colegio BomTrato Unidade II', $created->name);
        $this->assertTrue($created->legalEntity->is($legalEntityB));
    }

    public function test_unauthorized_user_cannot_access_legal_entities_or_schools_in_filament(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListLegalEntities::class)->assertForbidden();
        Livewire::test(ListSchools::class)->assertForbidden();
    }
}
