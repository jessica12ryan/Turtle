<?php

namespace App\Core;

/**
 * Single source of truth for "does this property still have tenants?".
 *
 * A tenancy only counts as current when the property_tenant link has not been
 * released (moved_out_at IS NULL) AND the tenant account is not archived
 * (users.archived_at IS NULL). Testing moved_out_at on its own is fragile:
 * if the two fields ever drift apart (a tenant archived through another path,
 * a restored account, manually edited rows) a property keeps claiming tenants
 * that are clearly archived. Every occupancy check should go through here.
 */
class Tenancy
{
    /**
     * SQL fragment for a joined property_tenant/users pair.
     */
    public static function activeLinkCondition(string $ptAlias = 'pt', string $userAlias = 'u'): string
    {
        return "{$ptAlias}.moved_out_at IS NULL AND {$userAlias}.archived_at IS NULL";
    }

    /**
     * Correlated count of current tenants for a property alias.
     */
    public static function tenantCountSql(string $propertyAlias = 'p'): string
    {
        return "(SELECT COUNT(*) FROM property_tenant pt_count
                 JOIN users u_count ON u_count.id = pt_count.tenant_id
                 WHERE pt_count.property_id = {$propertyAlias}.id
                   AND pt_count.moved_out_at IS NULL
                   AND u_count.archived_at IS NULL)";
    }

    /**
     * Correlated lookup of the current main tenant for a property alias.
     */
    public static function mainTenantIdSql(string $propertyAlias = 'p'): string
    {
        return "(SELECT pt_main.tenant_id FROM property_tenant pt_main
                 JOIN users u_main ON u_main.id = pt_main.tenant_id
                 WHERE pt_main.property_id = {$propertyAlias}.id
                   AND pt_main.is_main_tenant = 1
                   AND pt_main.moved_out_at IS NULL
                   AND u_main.archived_at IS NULL
                 ORDER BY pt_main.id LIMIT 1)";
    }

    /**
     * Correlated EXISTS check for a property alias. Prefix with "NOT " when
     * looking for vacant properties.
     */
    public static function hasTenantSql(string $propertyAlias = 'p'): string
    {
        return "EXISTS (SELECT 1 FROM property_tenant pt_exists
                 JOIN users u_exists ON u_exists.id = pt_exists.tenant_id
                 WHERE pt_exists.property_id = {$propertyAlias}.id
                   AND pt_exists.moved_out_at IS NULL
                   AND u_exists.archived_at IS NULL)";
    }
}
