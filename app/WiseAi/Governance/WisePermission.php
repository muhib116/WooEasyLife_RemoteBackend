<?php

namespace App\WiseAi\Governance;

use App\Models\User;
use App\Services\RbacService;

/**
 * Hub RBAC helpers for Wise Knowledge editor vs publisher.
 *
 * Grandfather: if user has no wise.knowledge.* perms yet, dashboard.view keeps working.
 */
class WisePermission
{
    public const EDIT = 'wise.knowledge.edit';

    public const PUBLISH = 'wise.knowledge.publish';

    public function __construct(
        private RbacService $rbac,
    ) {}

    public function canEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->rbac->hasPermission($user, self::EDIT) || $this->rbac->hasPermission($user, self::PUBLISH)) {
            return true;
        }

        return $this->isGrandfathered($user);
    }

    public function canPublish(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->rbac->hasPermission($user, self::PUBLISH)) {
            return true;
        }

        return $this->isGrandfathered($user);
    }

    private function isGrandfathered(User $user): bool
    {
        if ($this->rbac->hasPermission($user, self::EDIT) || $this->rbac->hasPermission($user, self::PUBLISH)) {
            return false;
        }

        // Only true admins keep pre-RBAC access — not every dashboard.view role
        // (Viewer / billing-clerk must not inherit Platform Train + publish).
        $role = strtolower((string) ($user->role ?? ''));
        if (! in_array($role, ['admin', 'super_admin', 'administrator'], true)) {
            return false;
        }

        return $this->rbac->hasPermission($user, 'dashboard.view');
    }
}
