<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class HomeController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();
        $role = $user['role'];

        if ($role === 'tenant') {
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
                 AND t.status IN ('open', 'in_progress')
                 ORDER BY t.created_at DESC LIMIT 10",
                [$auth->id()]
            );

            $allTickets = Database::fetch(
                "SELECT COUNT(*) as cnt FROM tickets 
                 WHERE property_id IN (SELECT property_id FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL) 
                 AND archived_at IS NULL",
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

            $stats = [];
            $stats['properties'] = count($properties);
            $stats['tickets'] = $allTickets['cnt'] ?? 0;
            $stats['leases'] = count($leases);
            $stats['open_tickets'] = count($openTickets);

            $alerts = [];
            $myLeases = Database::fetch(
                "SELECT COUNT(*) as cnt FROM leases l 
                 JOIN property_tenant pt ON pt.property_id = l.property_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL",
                [$auth->id()]
            );
            if (!$myLeases || $myLeases['cnt'] === 0) {
                $alerts['warning'][] = ['msg' => 'No leases assigned to you yet.', 'link' => '/leases'];
            }

            $recentTickets = [];

            $view = new View();
            $view->layout('layouts/main', ['title' => 'Home']);
            $view->render('home/index', compact('alerts', 'stats', 'role', 'properties', 'openTickets', 'leases', 'recentTickets'));
            return;
        }

        $alerts = [];

        if ($role === 'admin') {
            $adminCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin' AND archived_at IS NULL");
            if (!$adminCount || $adminCount['cnt'] === 0) {
                $alerts['critical'][] = ['msg' => 'No admin account exists. Create one to maintain system access.', 'link' => '/setup'];
            }

            $channel = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'update_channel'");
            $channel = $channel['value'] ?? 'stable';

            $latestFromGit = \App\Controllers\UpdateController::getLatestVersion($channel);
            if ($latestFromGit) {
                Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'latest_version'", [$latestFromGit]);
                Database::execute("UPDATE settings SET `value` = ? WHERE `key` = 'last_update_check'", [date('Y-m-d H:i:s')]);
                $currentVer = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'app_version'");
                $current = $currentVer['value'] ?? '0.0.0';
                if ($channel === 'development') {
                    if ($latestFromGit !== $current) {
                        $alerts['warning'][] = ['msg' => 'Development update (' . h($latestFromGit) . ') is available.', 'link' => '/settings?tab=updates'];
                    }
                } else {
                    if (version_compare($latestFromGit, $current, '>')) {
                        $alerts['warning'][] = ['msg' => 'Update v' . h($latestFromGit) . ' is available.', 'link' => '/settings?tab=updates'];
                    }
                }
            }

            $landlordCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'landlord' AND archived_at IS NULL");
            if (!$landlordCount || $landlordCount['cnt'] === 0) {
                $alerts['critical'][] = ['msg' => 'No landlord account exists. Create one to manage properties.', 'link' => '/staff/create'];
            }

            $lastBackup = Database::fetch("SELECT MAX(created_at) as last FROM documents LIMIT 1");
            if (!$lastBackup || !$lastBackup['last']) {
                $alerts['warning'][] = ['msg' => 'No lease documents have been uploaded yet.', 'link' => '/leases/create'];
            }

            $archivedProperties = Database::fetch("SELECT COUNT(*) as cnt FROM properties WHERE archived_at IS NOT NULL");
            if ($archivedProperties && $archivedProperties['cnt'] > 0) {
                $alerts['warning'][] = ['msg' => $archivedProperties['cnt'] . ' archived propert' . ($archivedProperties['cnt'] > 1 ? 'ies' : 'y') . ' exist. Consider permanent cleanup.', 'link' => '/properties'];
            }

            $ntp = checkNtpTime();
            if ($ntp !== null && $ntp['drift'] > 60) {
                $alerts['warning'][] = ['msg' => 'System time differs from NTP by ' . $ntp['drift'] . ' seconds. Consider syncing your server clock.', 'link' => '/settings?tab=general'];
            } elseif ($ntp === null) {
                $lastNtpFail = \App\Core\Database::fetch("SELECT `value` FROM settings WHERE `key` = 'last_ntp_check'");
                $lastFailTs = $lastNtpFail['value'] ?? '';
                // Only show the NTP warning on first failure, not every page load
                if (!$lastFailTs || (strtotime('now') - strtotime($lastFailTs)) < 60) {
                    $alerts['warning'][] = ['msg' => 'Cannot reach NTP server for time sync. Check your NTP server setting or network connectivity.', 'link' => '/settings?tab=general'];
                }
            }
        }

        if (in_array($role, ['admin', 'landlord', 'property_manager'])) {
            $propCount = Database::fetch("SELECT COUNT(*) as cnt FROM properties WHERE archived_at IS NULL");
            if (!$propCount || $propCount['cnt'] === 0) {
                $isAdmin = $role === 'admin';
                $alerts[$isAdmin ? 'warning' : 'critical'][] = ['msg' => 'No properties exist. Create one to get started.', 'link' => '/properties/create'];
            } else {
                $noTenantProps = Database::fetch("SELECT COUNT(*) as cnt FROM properties p WHERE p.archived_at IS NULL AND NOT EXISTS (SELECT 1 FROM property_tenant pt WHERE pt.property_id = p.id AND pt.moved_out_at IS NULL)");
                if ($noTenantProps && $noTenantProps['cnt'] > 0) {
                    $alerts['warning'][] = ['msg' => $noTenantProps['cnt'] . ' propert' . ($noTenantProps['cnt'] > 1 ? 'ies have' : 'y has') . ' no tenants. Add tenants to enable lease uploads.', 'link' => '/tenants/create'];
                }
            }

            $tenantCount = Database::fetch("SELECT COUNT(*) as cnt FROM users u JOIN property_tenant pt ON pt.tenant_id = u.id WHERE pt.moved_out_at IS NULL AND u.archived_at IS NULL");
            if (!$tenantCount || $tenantCount['cnt'] === 0) {
                $isAdmin = $role === 'admin';
                $alerts[$isAdmin ? 'warning' : 'critical'][] = ['msg' => 'No tenants exist. Add tenants to your properties.', 'link' => '/tenants/create'];
            }

            $openTickets = Database::fetch("SELECT COUNT(*) as cnt FROM tickets WHERE status IN ('open','in_progress') AND archived_at IS NULL");
            if ($openTickets && $openTickets['cnt'] > 5) {
                $alerts['warning'][] = ['msg' => $openTickets['cnt'] . ' open/in-progress tickets need attention.', 'link' => '/tickets'];
            }
        }

        if ($role === 'admin' || $role === 'landlord') {
            $staffCount = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role IN ('property_manager','maintenance') AND archived_at IS NULL");
            if (!$staffCount || $staffCount['cnt'] === 0) {
                $alerts['warning'][] = ['msg' => 'No staff members exist. Invite property managers or maintenance staff.', 'link' => '/staff/create'];
            }
        }

        $stats = [];
        if (in_array($role, ['admin', 'landlord', 'property_manager'])) {
            $isAdmin = $role === 'admin';
            if ($isAdmin) {
                $propertyIds = "SELECT id FROM properties WHERE archived_at IS NULL";
            } else {
                $companyIds = Database::fetchAll(
                    "SELECT company_id FROM company_user WHERE user_id = ?",
                    [$auth->id()]
                );
                $companyIds = array_column($companyIds, 'company_id');
                $companyIdList = implode(',', array_map('intval', $companyIds)) ?: '0';
                $propertyIds = "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL";
            }

            $props = Database::fetchAll("SELECT id FROM properties WHERE id IN ({$propertyIds}) AND archived_at IS NULL");
            $propertyIdList = implode(',', array_column($props, 'id')) ?: '0';

            $stats['properties'] = count($props);
            $stats['tenants'] = Database::fetch(
                "SELECT COUNT(*) as count FROM property_tenant WHERE property_id IN ({$propertyIdList}) AND moved_out_at IS NULL"
            )['count'] ?? 0;
            $stats['leases'] = Database::fetch(
                "SELECT COUNT(*) as count FROM leases WHERE property_id IN ({$propertyIdList}) AND archived_at IS NULL"
            )['count'] ?? 0;
            $stats['open_tickets'] = Database::fetch(
                "SELECT COUNT(*) as count FROM tickets WHERE property_id IN ({$propertyIdList}) AND status IN ('open','in_progress') AND archived_at IS NULL"
            )['count'] ?? 0;

            $recentTickets = Database::fetchAll(
                "SELECT t.*, p.name as property_name, u.name as tenant_name FROM tickets t 
                 JOIN properties p ON p.id = t.property_id 
                 JOIN users u ON u.id = t.tenant_id 
                 WHERE t.property_id IN ({$propertyIdList}) AND t.archived_at IS NULL 
                 ORDER BY t.created_at DESC LIMIT 5"
            );
        } elseif ($role === 'tenant') {
            $uid = $auth->id();
            $stats['properties'] = Database::fetch("SELECT COUNT(*) as cnt FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL", [$uid])['cnt'] ?? 0;
            $stats['tickets'] = Database::fetch("SELECT COUNT(*) as cnt FROM tickets WHERE tenant_id = ? AND archived_at IS NULL", [$uid])['cnt'] ?? 0;
            $stats['leases'] = Database::fetch("SELECT COUNT(*) as cnt FROM leases l JOIN property_tenant pt ON pt.property_id = l.property_id WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL", [$uid])['cnt'] ?? 0;
            $recentTickets = [];
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Home']);
        $view->render('home/index', compact('alerts', 'stats', 'role', 'recentTickets'));
    }
}
