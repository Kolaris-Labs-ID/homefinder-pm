<?php

namespace App\Policies\Hris;

use App\Models\Hris\FinanceAttachment;
use App\Models\User;

/**
 * Download/delete authorization for finance attachments (EXPENSE_MODULE.MD Phase 3).
 * Locked-state is enforced by FinanceAttachmentService::delete (422), not here.
 */
class FinanceAttachmentPolicy
{
    public function download(User $user, FinanceAttachment $attachment): bool
    {
        if ($this->hasFinancePermission($user, 'finance_attachments.download')) {
            return true;
        }

        if ((int) $attachment->uploaded_by === (int) $user->id) {
            return true;
        }

        return $this->ownsAttachable($user, $attachment);
    }

    public function delete(User $user, FinanceAttachment $attachment): bool
    {
        if ($this->ownsAttachable($user, $attachment)) {
            return true;
        }

        return (int) $attachment->uploaded_by === (int) $user->id
            && $this->hasFinancePermission($user, 'finance_attachments.delete_own_unlocked');
    }

    /**
     * Guard-explicit permission check. Permissions are seeded on the `web` guard;
     * API requests authenticate on the `sanctum` guard, so plain can() can miss them.
     */
    private function hasFinancePermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission, 'web');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }

    private function ownsAttachable(User $user, FinanceAttachment $attachment): bool
    {
        $ownerEmployeeId = $attachment->attachable->employee_id ?? null;

        return $ownerEmployeeId !== null
            && $user->employee
            && (int) $ownerEmployeeId === (int) $user->employee->id;
    }
}
