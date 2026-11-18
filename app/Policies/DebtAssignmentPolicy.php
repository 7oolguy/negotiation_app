<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DebtAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DebtAssignmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DebtAssignment')
            || $authUser->can('ViewAny:Debt')
            || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector']);
    }

    public function view(AuthUser $authUser, DebtAssignment $assignment): bool
    {
        $canView = $authUser->can('View:DebtAssignment')
            || $authUser->can('View:Debt')
            || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector']);

        return $canView && $this->canAccess($authUser, $assignment);
    }

    public function create(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser)
            && ($authUser->can('Create:DebtAssignment') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager']));
    }

    public function update(AuthUser $authUser, DebtAssignment $assignment): bool
    {
        return ! $this->isReadOnly($authUser)
            && ($authUser->can('Update:DebtAssignment') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager']))
            && $this->canAccess($authUser, $assignment);
    }

    public function delete(AuthUser $authUser, DebtAssignment $assignment): bool
    {
        return ! $this->isReadOnly($authUser)
            && ($authUser->can('Delete:DebtAssignment') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager']))
            && $this->canAccess($authUser, $assignment);
    }

    private function canAccess(AuthUser $authUser, DebtAssignment $assignment): bool
    {
        $schoolId = $assignment->debt?->school_id;

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
