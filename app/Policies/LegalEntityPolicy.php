<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LegalEntity;
use Illuminate\Auth\Access\HandlesAuthorization;

class LegalEntityPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LegalEntity');
    }

    public function view(AuthUser $authUser, LegalEntity $legalEntity): bool
    {
        return $authUser->can('View:LegalEntity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LegalEntity');
    }

    public function update(AuthUser $authUser, LegalEntity $legalEntity): bool
    {
        return $authUser->can('Update:LegalEntity');
    }

    public function delete(AuthUser $authUser, LegalEntity $legalEntity): bool
    {
        return $authUser->can('Delete:LegalEntity');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LegalEntity');
    }

    public function restore(AuthUser $authUser, LegalEntity $legalEntity): bool
    {
        return $authUser->can('Restore:LegalEntity');
    }

    public function forceDelete(AuthUser $authUser, LegalEntity $legalEntity): bool
    {
        return $authUser->can('ForceDelete:LegalEntity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LegalEntity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LegalEntity');
    }

    public function replicate(AuthUser $authUser, LegalEntity $legalEntity): bool
    {
        return $authUser->can('Replicate:LegalEntity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LegalEntity');
    }

}