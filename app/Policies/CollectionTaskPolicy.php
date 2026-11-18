<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CollectionTask;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CollectionTaskPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CollectionTask')
            || $authUser->can('ViewAny:Debt')
            || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal', 'finance']);
    }

    public function view(AuthUser $authUser, CollectionTask $task): bool
    {
        if (! $this->canAccess($authUser, $task)) {
            return false;
        }

        if (method_exists($authUser, 'isCollectorOnly') && $authUser->isCollectorOnly()) {
            return (int) $task->assigned_to === (int) $authUser->getKey();
        }

        return $authUser->can('View:CollectionTask')
            || $authUser->can('View:Debt')
            || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'legal', 'finance']);
    }

    public function create(AuthUser $authUser): bool
    {
        return ! $this->isReadOnly($authUser)
            && ($authUser->can('Create:CollectionTask') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager', 'collector', 'legal']));
    }

    public function update(AuthUser $authUser, CollectionTask $task): bool
    {
        if ($this->isReadOnly($authUser) || ! $this->canAccess($authUser, $task)) {
            return false;
        }

        if ($authUser->can('Update:CollectionTask') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager'])) {
            return true;
        }

        return $authUser->hasRole('collector') && (int) $task->assigned_to === (int) $authUser->getKey();
    }

    public function delete(AuthUser $authUser, CollectionTask $task): bool
    {
        return ! $this->isReadOnly($authUser)
            && $this->canAccess($authUser, $task)
            && ($authUser->can('Delete:CollectionTask') || $authUser->hasAnyRole(['super_admin', 'superadmin', 'manager']));
    }

    private function canAccess(AuthUser $authUser, CollectionTask $task): bool
    {
        $schoolId = $task->debt?->school_id;

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
