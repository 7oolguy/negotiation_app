<?php

namespace Tests\Feature;

use App\Filament\Resources\Schools\Pages\CreateSchool;
use App\Filament\Resources\Schools\Pages\EditSchool;
use App\Filament\Resources\Schools\Pages\ListSchools;
use App\Filament\Resources\Schools\Pages\ViewSchool;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrossSchoolIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function createSchoolViewer(School $school): User
    {
        $role = Role::firstOrCreate(['name' => 'school_viewer']);

        // Grant ViewAny/View, and even if Update/Create were granted, policy must keep school_viewer read-only.
        $permissions = ['ViewAny:School', 'View:School', 'Create:School', 'Update:School', 'Delete:School'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->schools()->attach($school);

        return $user;
    }

    public function test_school_a_user_can_only_list_and_view_school_a(): void
    {
        $schoolA = School::factory()->create(['name' => 'School A']);
        $schoolB = School::factory()->create(['name' => 'School B']);

        $userA = $this->createSchoolViewer($schoolA);

        $this->actingAs($userA);

        Livewire::test(ListSchools::class)
            ->assertCanSeeTableRecords([$schoolA])
            ->assertCanNotSeeTableRecords([$schoolB]);

        Livewire::test(ViewSchool::class, ['record' => $schoolA->getRouteKey()])
            ->assertOk();

        $this->assertTrue($userA->can('view', $schoolA));
        $this->assertFalse($userA->can('view', $schoolB));
    }

    public function test_school_a_user_cannot_forge_school_b_id_in_view_or_edit_routes(): void
    {
        $schoolA = School::factory()->create(['name' => 'School A']);
        $schoolB = School::factory()->create(['name' => 'School B']);

        $userA = $this->createSchoolViewer($schoolA);

        $this->actingAs($userA);

        // Forged School B URLs are forbidden (403) and Livewire route binding scopes out School B.
        $this->get(ViewSchool::getUrl(['record' => $schoolB]))->assertForbidden();
        $this->get(EditSchool::getUrl(['record' => $schoolB]))->assertForbidden();

        Livewire::test(ViewSchool::class, ['record' => $schoolB->getRouteKey()])->assertNotFound();
    }

    public function test_school_viewer_is_strictly_read_only_even_for_assigned_school(): void
    {
        $schoolA = School::factory()->create(['name' => 'School A']);
        $userA = $this->createSchoolViewer($schoolA);

        $this->actingAs($userA);

        $this->assertFalse($userA->can('create', School::class));
        $this->assertFalse($userA->can('update', $schoolA));
        $this->assertFalse($userA->can('delete', $schoolA));

        Livewire::test(CreateSchool::class)->assertForbidden();
        Livewire::test(EditSchool::class, ['record' => $schoolA->getRouteKey()])->assertForbidden();
    }

    public function test_query_scope_isolates_school_viewer_but_allows_super_admin(): void
    {
        $schoolA = School::factory()->create(['name' => 'School A']);
        $schoolB = School::factory()->create(['name' => 'School B']);

        $userA = $this->createSchoolViewer($schoolA);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $admin = User::factory()->create();
        $admin->assignRole($superAdminRole);

        $this->assertEquals([$schoolA->id], School::forUser($userA)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$schoolA->id, $schoolB->id], School::forUser($admin)->pluck('id')->all());
    }
}
