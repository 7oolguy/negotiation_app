<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CollectionAction;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CollectionActionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CollectionAction')
            || $authUser->can('ViewAny:Debt')
            || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal', 'finance']);
    }

    public function view(AuthUser $authUser, CollectionAction $action): bool
    {
        // Internal notes and privileged legal notes must never be exposed to school users.
        if ($this->isReadOnly($authUser) && $action->is_internal) {
            return false;
        }

        $canView = $authUser->can('View:CollectionAction')
            || $authUser->can('View:Debt')
            || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal', 'finance']);

        return $canView && $this->canAccess($authUser, $action);
    }

    public function create(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser)
            && ($authUser->can('Create:CollectionAction') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal', 'finance']));
    }

    public function update(AuthUser $authUser, CollectionAction $action): bool
    {
        if ($this->isReadOnly($authUser) || ! $this->canAccess($authUser, $action)) {
            return false;
        }

        if ($authUser->can('Update:CollectionAction') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager'])) {
            return true;
        }

        return $authUser->hasRole('collector') && (int) $action->user_id === (int) $authUser->getKey();
    }

    public function delete(AuthUser $authUser, CollectionAction $action): bool
    {
        return ! $this->isReadOnly($authUser)
            && $this->canAccess($authUser, $action)
            && ($authUser->can('Delete:CollectionAction') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager']));
    }

    private function canAccess(AuthUser $authUser, CollectionAction $action): bool
    {
        $schoolId = $action->debt?->school_id;

        if ($schoolId === null) {
            return false;
        }

        return ! method_exists($authUser, 'canAccessSchoolId')
            || $authUser->canAccessSchoolId((int) $schoolId);
    }

    private function isReadOnly(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'isSchoolScoped') && $authUser->isSchoolScoped();
    }
}
