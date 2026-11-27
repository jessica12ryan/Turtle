<?php

namespace App\Core;

/**
 * Per-resource authorization.
 *
 * Route middleware answers "may this role use this feature?"; these checks
 * answer "may this user touch *this* record?". List queries are scoped by
 * company/property manager, but every by-ID endpoint must pass through here
 * as well — otherwise a valid permission plus a guessed ID is enough to read
 * or change somebody else's data.
 */
class Access
{
    private const LEVELS = [
        'admin' => 0,
        'landlord' => 1,
        'property_manager' => 2,
        'maintenance' => 3,
        'tenant' => 4,
    ];

    public static function user(?array $user = null): ?array
    {
        return $user ?? Auth::instance()->user();
    }

    /** True only when the account is a tenant without any staff role. */
    private static function isTenantOnly(array $user): bool
    {
        return \is_tenant_only_user($user);
    }

    /** Whether a user may view another user's avatar. */
    public static function avatar(int $userId, ?array $actor = null): bool
    {
        $target = Database::fetch(
            "SELECT id, archived_at FROM users WHERE id = ?",
            [$userId]
        );
        if (!$target || $target['archived_at'] !== null) {
            return false;
        }

        $actor = self::user($actor);
        if (!$actor) {
            return false;
        }
        if ((int)($actor['id'] ?? 0) === $userId || \is_admin_user($actor)) {
            return true;
        }

        if (\is_tenant_only_user($actor)) {
            // A tenant may see the avatar of the landlord/PM of their property.
            $row = Database::fetch(
                "SELECT 1 FROM property_tenant pt
                 JOIN properties p ON p.id = pt.property_id
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL
                   AND (p.landlord_id = ? OR p.property_manager_id = ?) LIMIT 1",
                [$actor['id'], $userId, $userId]
            );
            return (bool)$row;
        }

        // Staff may see avatars of other staff in a shared company.
        $actorCompanies = \user_company_ids((int)($actor['id'] ?? 0));
        $targetCompanies = \user_company_ids($userId);
        return (bool)array_intersect($actorCompanies, $targetCompanies);
    }

    /** Abort with a 403 for the current request. */
    public static function deny(): void
    {
        http_response_code(403);
        require base_path('www/Views/errors/403.php');
        exit;
    }

    /** Require a resource check, aborting on failure. */
    public static function require(bool $allowed): void
    {
        if (!$allowed) {
            self::deny();
        }
    }

    /**
     * A staff member may only manage users below them in the role hierarchy
     * and within a company they both belong to. Admins may manage any user.
     */
    public static function canManageUser(array $target, ?array $actor = null): bool
    {
        $actor = self::user($actor);
        if (!$actor) {
            return false;
        }
        if (($actor['role'] ?? '') === 'admin') {
            return true;
        }
        if ((int)($actor['id'] ?? 0) === (int)($target['id'] ?? 0)) {
            // Editing one's own profile is allowed; role changes are already
            // restricted in StaffController.
            return true;
        }

        $actorLevel = self::LEVELS[$actor['role'] ?? ''] ?? 99;
        $targetLevel = self::LEVELS[$target['role'] ?? ''] ?? 99;
        if ($actorLevel >= $targetLevel) {
            return false;
        }

        $actorCompanies = array_map('intval', array_column(
            Database::fetchAll("SELECT company_id FROM company_user WHERE user_id = ?", [$actor['id']]),
            'company_id'
        ));
        $targetCompanies = array_map('intval', array_column(
            Database::fetchAll("SELECT company_id FROM company_user WHERE user_id = ?", [$target['id']]),
            'company_id'
        ));

        return (bool) array_intersect($actorCompanies, $targetCompanies);
    }

    public static function property(int $propertyId, ?array $user = null): bool
    {
        $user = self::user($user);
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        if (self::isTenantOnly($user)) {
            $row = Database::fetch(
                "SELECT 1 FROM property_tenant pt
                 JOIN properties p ON p.id = pt.property_id
                 WHERE pt.property_id = ? AND pt.tenant_id = ?
                   AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$propertyId, $user['id']]
            );
            return (bool) $row;
        }
        $property = Database::fetch(
            "SELECT id, company_id, property_manager_id FROM properties WHERE id = ?",
            [$propertyId]
        );
        return $property ? \user_can_access_property($user, $property) : false;
    }

    public static function tenant(int $tenantId, ?array $user = null): bool
    {
        $user = self::user($user);
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        if ((int)($user['id'] ?? 0) === $tenantId) {
            return true;
        }
        if (self::isTenantOnly($user)) {
            return false;
        }
        $links = Database::fetchAll(
            "SELECT DISTINCT property_id FROM property_tenant WHERE tenant_id = ?",
            [$tenantId]
        );
        foreach ($links as $link) {
            if (self::property((int)$link['property_id'], $user)) {
                return true;
            }
        }
        return false;
    }

    public static function application(int $applicationId, ?array $user = null): bool
    {
        $user = self::user($user);
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        $row = Database::fetch(
            "SELECT p.company_id, p.property_manager_id
             FROM tenant_applications a
             LEFT JOIN properties p ON p.id = a.property_id
             WHERE a.id = ?",
            [$applicationId]
        );
        if (!$row) {
            return false;
        }
        $property = [
            'company_id' => $row['company_id'] ?? 0,
            'property_manager_id' => $row['property_manager_id'] ?? 0,
        ];
        return \user_can_access_property($user, $property);
    }

    public static function lease(int $leaseId, ?array $user = null): bool
    {
        $row = Database::fetch("SELECT property_id FROM leases WHERE id = ?", [$leaseId]);
        return $row ? self::property((int)$row['property_id'], $user) : false;
    }

    public static function document(int $documentId, ?array $user = null): bool
    {
        $user = self::user($user);
        if (!$user) {
            return false;
        }
        $doc = Database::fetch(
            "SELECT documentable_type, documentable_id, uploaded_by FROM documents WHERE id = ?",
            [$documentId]
        );
        if (!$doc) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        if (($doc['documentable_type'] ?? '') === 'lease') {
            return self::lease((int)$doc['documentable_id'], $user);
        }
        if (($doc['documentable_type'] ?? '') === 'property') {
            return self::property((int)$doc['documentable_id'], $user);
        }
        return (int)($doc['uploaded_by'] ?? 0) === (int)($user['id'] ?? 0);
    }

    public static function payment(int $paymentId, ?array $user = null): bool
    {
        $row = Database::fetch(
            "SELECT property_id FROM payments WHERE id = ?",
            [$paymentId]
        );
        return $row ? self::property((int)$row['property_id'], $user) : false;
    }

    public static function photo(int $photoId, ?array $user = null): bool
    {
        $row = Database::fetch("SELECT property_id FROM property_photos WHERE id = ?", [$photoId]);
        return $row ? self::property((int)$row['property_id'], $user) : false;
    }

    public static function ticket(int $ticketId, ?array $user = null): bool
    {
        $user = self::user($user);
        if (!$user) {
            return false;
        }
        $row = Database::fetch("SELECT property_id, tenant_id FROM tickets WHERE id = ?", [$ticketId]);
        if (!$row) {
            return false;
        }
        if (self::isTenantOnly($user)) {
            if ((int)$row['tenant_id'] === (int)($user['id'] ?? 0)) {
                return true;
            }
            $link = Database::fetch(
                "SELECT 1 FROM property_tenant
                 WHERE tenant_id = ? AND property_id = ? AND moved_out_at IS NULL",
                [$user['id'], $row['property_id']]
            );
            return (bool) $link;
        }
        return self::property((int)$row['property_id'], $user);
    }
}
