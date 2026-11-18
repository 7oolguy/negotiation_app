<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Debt;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Debt');
    }

    public function view(AuthUser $authUser, Debt $debt): bool
    {
        return $authUser->can('View:Debt') && $this->canAccess($authUser, $debt);
    }

    public function create(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('Create:Debt');
    }

    public function update(AuthUser $authUser, Debt $debt): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Update:Debt')
            && $this->canAccess($authUser, $debt);
    }

    public function delete(AuthUser $authUser, Debt $debt): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Delete:Debt')
            && $this->canAccess($authUser, $debt);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('DeleteAny:Debt');
    }

    public function restore(AuthUser $authUser, Debt $debt): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Restore:Debt')
            && $this->canAccess($authUser, $debt);
    }

    public function forceDelete(AuthUser $authUser, Debt $debt): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('ForceDelete:Debt')
            && $this->canAccess($authUser, $debt);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('ForceDeleteAny:Debt');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('RestoreAny:Debt');
    }

    public function replicate(AuthUser $authUser, Debt $debt): bool
    {
        return ! $this->isReadOnly($authUser)
            && $authUser->can('Replicate:Debt')
            && $this->canAccess($authUser, $debt);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser) && $authUser->can('Reorder:Debt');
    }

    private function canAccess(AuthUser $authUser, Debt $debt): bool
    {
        return ! method_exists($authUser, 'canAccessSchoolId')
            || $authUser->canAccessSchoolId((int) $debt->school_id);
    }

    private function isReadOnly(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'isSchoolScoped') && $authUser->isSchoolScoped();
    }
}
