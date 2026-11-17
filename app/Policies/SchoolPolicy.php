<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\School;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchoolPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:School');
    }

    public function view(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('View:School') && $this->canAccess($authUser, $school);
    }

    public function create(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('Create:School');
    }

    public function update(AuthUser $authUser, School $school): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Update:School')
            && $this->canAccess($authUser, $school);
    }

    public function delete(AuthUser $authUser, School $school): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Delete:School')
            && $this->canAccess($authUser, $school);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('DeleteAny:School');
    }

    public function restore(AuthUser $authUser, School $school): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Restore:School')
            && $this->canAccess($authUser, $school);
    }

    public function forceDelete(AuthUser $authUser, School $school): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('ForceDelete:School')
            && $this->canAccess($authUser, $school);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('ForceDeleteAny:School');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('RestoreAny:School');
    }

    public function replicate(AuthUser $authUser, School $school): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Replicate:School')
            && $this->canAccess($authUser, $school);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('Reorder:School');
    }

    private function canAccess(AuthUser $authUser, School $school): bool
    {
        return ! method_exists($authUser, 'canAccessSchool') || $authUser->canAccessSchool($school);
    }

    private function isReadOnly(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'isSchoolScoped') && $authUser->isSchoolScoped();
    }

}