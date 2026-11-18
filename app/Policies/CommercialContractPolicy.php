<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CommercialContract;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommercialContractPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CommercialContract');
    }

    public function view(AuthUser $authUser, CommercialContract $commercialContract): bool
    {
        return $authUser->can('View:CommercialContract');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CommercialContract');
    }

    public function update(AuthUser $authUser, CommercialContract $commercialContract): bool
    {
        return $authUser->can('Update:CommercialContract');
    }

    public function delete(AuthUser $authUser, CommercialContract $commercialContract): bool
    {
        return $authUser->can('Delete:CommercialContract');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CommercialContract');
    }

    public function restore(AuthUser $authUser, CommercialContract $commercialContract): bool
    {
        return $authUser->can('Restore:CommercialContract');
    }

    public function forceDelete(AuthUser $authUser, CommercialContract $commercialContract): bool
    {
        return $authUser->can('ForceDelete:CommercialContract');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CommercialContract');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CommercialContract');
    }

    public function replicate(AuthUser $authUser, CommercialContract $commercialContract): bool
    {
        return $authUser->can('Replicate:CommercialContract');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CommercialContract');
    }

}