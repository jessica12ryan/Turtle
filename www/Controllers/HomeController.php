<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Tenancy;
use App\Core\RentLedger;

class HomeController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();
        $role = $user['role'];
        $isAdmin = is_admin_user($user);
        $isTenant = is_tenant_only_user($user);

        if ($isTenant) {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN property_tenant pt ON pt.property_id = p.id 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$auth->id()]
            );

            $openTickets = Database::fetchAll(
                "SELECT t.*, p.name as property_name, u.name as tenant_name FROM tickets t 
                 JOIN properties p ON p.id = t.property_id 
                 JOIN users u ON u.id = t.tenant_id 
                 WHERE t.property_id IN (SELECT property_id FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL) 
                 AND t.archived_at IS NULL 
                 AND t.status IN ('open', 'in_progress', 'awaiting_parts', 'awaiting_contractor')
                 ORDER BY t.created_at DESC LIMIT 10",
                [$auth->id()]
            );

            $allTickets = Database::fetch(
                "SELECT COUNT(*) as cnt FROM tickets 
                 WHERE property_id IN (SELECT property_id FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL) 
                 AND archived_at IS NULL AND status != 'closed'",
                [$auth->id()]
            );

            $leases = Database::fetchAll(
                "SELECT l.*, p.name as property_name FROM leases l 
                 JOIN property_tenant pt ON pt.property_id = l.property_id 
                 JOIN properties p ON p.id = l.property_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND l.archived_at IS NULL 
                 ORDER BY l.created_at DESC LIMIT 5",
                [$auth->id()]
            );

            // Rent info for tenant home page
            $tenantProperties = Database::fetchAll(
                "SELECT p.id, p.name, p.rent_amount, p.rent_due_day, pt.id as property_tenant_id
                 FROM properties p
                 JOIN property_tenant pt ON pt.property_id = p.id
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$auth->id()]
            );
            $rentInfo = [];
            foreach ($tenantProperties as $tp) {
                $periodStatus = RentLedger::status((int) $tp['id'], $tp);
                $lastPayment = Database::fetch(
                    "SELECT payment_date, amount FROM payments WHERE property_tenant_id = ? ORDER BY payment_date DESC LIMIT 1",
                    [$tp['property_tenant_id']]
                );
                $tp['paid_amount'] = $periodStatus['paid_amount'];
                $tp['status'] = $periodStatus['status'];
                $tp['period_label'] = $periodStatus['label'];
                $tp['last_payment_date'] = $lastPayment['payment_date'] ?? null;
                $tp['last_payment_amount'] = $lastPayment['amount'] ?? null;
                $rentInfo[] = $tp;
            }

            $stats = [];
            $stats['properties'] = count($properties);
            $stats['tickets'] = $allTickets['cnt'] ?? 0;
            $stats['leases'] = count($leases);
            $stats['open_tickets'] = count($openTickets);

            $alerts = [];

            $recentTickets = [];

            $view = new View();
            $view->layout('layouts/main', ['title' => 'Home']);
            $view->render('home/index', compact('alerts', 'stats', 'role', 'properties', 'openTickets', 'leases', 'recentTickets', 'rentInfo'));
            return;
        }

        $alerts = [];

        // Telemetry opt-in nudge: the tenant branch returns above, so every
        // role reaching here is staff. Link to Settings only for admins.
        if (!\App\Core\Telemetry::isEnabled()) {
            $alerts['critical'][] = [
                'msg' => 'Sharing telemetry data is turned off. Turn it on to help with development.',
            ] + ($isAdmin ? ['link' => '/settings?tab=updates'] : []);
        }

        if ($isAdmin) {
            $adminCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND archived_at IS NULL");
            if (!$adminCount || $adminCount['cnt'] === 0) {
                $alerts['critical'][] = ['msg' => 'No admin account exists. Create one to maintain system access.', 'link' => '/setup'];
            }

            $channel = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_channel'");
            $channel = $channel['value'] ?? 'stable';

            // Refresh at most every few hours when an admin views the
            // dashboard; ordinary page loads only read the stored result.
            if (\App\Controllers\UpdateController::claimUpdateCheck(time())) {
                try {
                    (new \App\Controllers\UpdateController())->refresh();
                } catch (\Throwable $e) {
                    \App\Core\Logger::warning('Automatic update check failed: ' . $e->getMessage());
                }
            }

            // Use the result of the last check (manual or periodic).
            $latestFromGit = trim((string)(Database::fetch("SELECT `value` FROM settings WHERE `key` = 'latest_version'")['value'] ?? ''));
            $currentVer = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
            $current = $currentVer['value'] ?? '0.0.0';
            if ($latestFromGit !== '') {
                if ($channel === 'development') {
                    // app_version is a tag while latest_version is a commit
                    // hash here, so compare the persisted check result instead.
                    $updateAvailable = trim((string)(Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_available'")['value'] ?? ''));
                    if (has_available_update(['update_available' => $updateAvailable])) {
                        $alerts['warning'][] = ['msg' => 'Development update (' . $latestFromGit . ') is available.', 'link' => '/settings?tab=updates'];
                    }
                } elseif (version_compare($latestFromGit, $current, '>')) {
                    $alerts['warning'][] = ['msg' => 'Update v' . $latestFromGit . ' is available.', 'link' => '/settings?tab=updates'];
                }
            }

            $landlordCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'landlord' AND archived_at IS NULL");
            if (!$landlordCount || $landlordCount['cnt'] === 0) {
                $alerts['critical'][] = ['msg' => 'No landlord account exists. Create one to manage properties.', 'link' => '/staff/create'];
            }

            $lastBackup = Database::fetch("SELECT MAX(created_at) as last FROM documents LIMIT 1");
            if (!$lastBackup || !$lastBackup['last']) {
                $alerts['warning'][] = ['msg' => 'No documents have been uploaded yet.', 'link' => '/leases/create'];
            }

            $archivedProperties = Database::fetch("SELECT COUNT(*) as cnt FROM properties WHERE archived_at IS NOT NULL");
            if ($archivedProperties && $archivedProperties['cnt'] > 0) {
                $alerts['warning'][] = ['msg' => $archivedProperties['cnt'] . ' archived propert' . ($archivedProperties['cnt'] > 1 ? 'ies' : 'y') . ' exist. Consider permanent cleanup.', 'link' => '/properties'];
            }

            // Read the cached NTP result; the dashboard never performs the
            // network check itself (that happens from Settings -> General).
            $ntpStatus = trim((string)(Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_ntp_status'")['value'] ?? ''));
            $ntpCheck = trim((string)(Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_ntp_check'")['value'] ?? ''));
            if ($ntpStatus !== '' && $ntpStatus !== 'unreachable' && (int)$ntpStatus > 60) {
                $alerts['warning'][] = ['msg' => 'System time differs from NTP by ' . (int)$ntpStatus . ' seconds. Consider syncing your server clock.', 'link' => '/settings?tab=general'];
            } elseif ($ntpStatus === 'unreachable' && $ntpCheck !== '' && (time() - strtotime($ntpCheck)) < 86400) {
                $alerts['warning'][] = ['msg' => 'Cannot reach NTP server for time sync. Check your NTP server setting or network connectivity.', 'link' => '/settings?tab=general'];
            }
        }

        $propCount = Database::fetch("SELECT COUNT(*) as cnt FROM properties WHERE archived_at IS NULL");
        if (!$propCount || $propCount['cnt'] === 0) {
            $alerts[$isAdmin ? 'warning' : 'critical'][] = ['msg' => 'No properties exist. Create one to get started.', 'link' => '/properties/create'];
        } else {
            $noTenantProps = Database::fetch("SELECT COUNT(*) as cnt FROM properties p WHERE p.archived_at IS NULL AND NOT " . Tenancy::hasTenantSql('p'));
            if ($noTenantProps && $noTenantProps['cnt'] > 0) {
                $count = (int)$noTenantProps['cnt'];
                $key = $count > 1 ? '%d properties have no tenants.' : '%d property has no tenants.';
                $links = [
                    ['label' => __('Add Tenant'), 'href' => '/tenants/create'],
                    ['label' => __('View Applications'), 'href' => '/applications'],
                ];
                if (can('guest_chats.access')) {
                    $links[] = ['label' => __('View Guest Chats'), 'href' => '/guest-chats'];
                }
                $alerts['warning'][] = [
                    'msg' => sprintf(__($key), $count),
                    'links' => $links,
                ];
            }
        }

        $tenantCount = Database::fetch("SELECT COUNT(*) as cnt FROM users u JOIN property_tenant pt ON pt.tenant_id = u.id WHERE pt.moved_out_at IS NULL AND u.archived_at IS NULL");
        if (!$tenantCount || $tenantCount['cnt'] === 0) {
            $alerts[$isAdmin ? 'warning' : 'critical'][] = ['msg' => 'No tenants exist. Add tenants to your properties.', 'link' => '/tenants/create'];
        }

        $openTickets = Database::fetch("SELECT COUNT(*) as cnt FROM tickets WHERE status IN ('open','in_progress','awaiting_parts','awaiting_contractor') AND archived_at IS NULL");
        if ($openTickets && $openTickets['cnt'] > 5) {
            $alerts['warning'][] = ['msg' => $openTickets['cnt'] . ' open tickets need attention.', 'link' => '/tickets'];
        }

        if ($isAdmin || has_role($user, 'landlord')) {
            $staffCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE archived_at IS NULL AND (role IN ('property_manager','maintenance') OR FIND_IN_SET('property_manager', REPLACE(COALESCE(secondary_roles,''), ' ', '')) OR FIND_IN_SET('maintenance', REPLACE(COALESCE(secondary_roles,''), ' ', '')))");
            if (!$staffCount || $staffCount['cnt'] === 0) {
                $alerts['warning'][] = ['msg' => 'No staff members exist. Invite property managers or maintenance staff.', 'link' => '/staff/create'];
            }
        }

        $recentTickets = [];
        $stats = [];
        $propertyIdList = implode(',', accessible_property_ids($user)) ?: '0';

        $stats['properties'] = count(explode(',', $propertyIdList));
        $stats['tenants'] = Database::fetch(
            "SELECT COUNT(*) as count FROM property_tenant pt
             JOIN users u ON u.id = pt.tenant_id
             WHERE pt.property_id IN ({$propertyIdList})
               AND pt.moved_out_at IS NULL AND u.archived_at IS NULL"
        )['count'] ?? 0;
        $stats['leases'] = Database::fetch(
            "SELECT COUNT(*) as count FROM leases WHERE property_id IN ({$propertyIdList}) AND archived_at IS NULL"
        )['count'] ?? 0;
        $stats['occupied'] = Database::fetch(
            "SELECT COUNT(*) as count FROM properties WHERE id IN ({$propertyIdList}) AND archived_at IS NULL AND " . Tenancy::hasTenantSql('properties')
        )['count'] ?? 0;
        $stats['total_units'] = $stats['properties'];
        $stats['open_tickets'] = Database::fetch(
            "SELECT COUNT(*) as count FROM tickets WHERE property_id IN ({$propertyIdList}) AND status IN ('open','in_progress','awaiting_parts','awaiting_contractor') AND archived_at IS NULL"
        )['count'] ?? 0;

        try {
            $stats['applications'] = Database::fetch(
                "SELECT COUNT(*) as count FROM tenant_applications WHERE property_id IN ({$propertyIdList}) AND status IN ('new','in_progress') AND archived_at IS NULL"
            )['count'] ?? 0;
        } catch (\Throwable $e) {
            $stats['applications'] = 0;
        }

        $recentTickets = Database::fetchAll(
            "SELECT t.*, p.name as property_name, u.name as tenant_name FROM tickets t 
             JOIN properties p ON p.id = t.property_id 
             JOIN users u ON u.id = t.tenant_id 
             WHERE t.property_id IN ({$propertyIdList}) AND t.archived_at IS NULL 
             ORDER BY t.created_at DESC LIMIT 5"
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Home']);
        $view->render('home/index', compact('alerts', 'stats', 'role', 'recentTickets'));
    }
}
