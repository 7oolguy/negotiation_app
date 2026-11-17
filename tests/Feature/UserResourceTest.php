<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $permissions = [
            'ViewAny:User',
            'View:User',
            'Create:User',
            'Update:User',
            'Delete:User',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        $this->actingAs($admin);
    }

    public function test_can_list_create_and_edit_users_with_roles_in_filament(): void
    {
        $existingUser = User::factory()->create();
        $collectorRole = Role::firstOrCreate(['name' => 'collector']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$existingUser]);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Maria Collector',
                'email' => 'maria@bomtrato.com.br',
                'password' => 'secret-password-123',
                'roles' => [$collectorRole->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'maria@bomtrato.com.br')->firstOrFail();
        $this->assertTrue($created->hasRole('collector'));
        $this->assertTrue(Hash::check('secret-password-123', $created->password));

        Livewire::test(EditUser::class, ['record' => $created->getRouteKey()])
            ->fillForm([
                'name' => 'Maria Updated',
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $created->refresh();
        $this->assertSame('Maria Updated', $created->name);
        $this->assertTrue(Hash::check('secret-password-123', $created->password));
    }

    public function test_unauthorized_user_cannot_access_user_resource(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListUsers::class)->assertForbidden();
    }
}
