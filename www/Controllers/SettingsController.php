<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class SettingsController
{
    public function index(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => 'Settings']);
        $view->render('settings/index');
    }

    public function reset(): void
    {
        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            flash('error', 'Invalid security token.');
            redirect('/settings');
        }

        $admin = Auth::instance()->user();

        $resetAll = !empty($_POST['reset_all']);
        $resetProperties = $resetAll || !empty($_POST['reset_properties']);
        $resetTenants = $resetAll || !empty($_POST['reset_tenants']);
        $resetStaff = $resetAll || !empty($_POST['reset_staff']);
        $resetLeases = $resetAll || !empty($_POST['reset_leases']);
        $resetTickets = $resetAll || !empty($_POST['reset_tickets']);

        if ($resetLeases) {
            $leases = Database::fetchAll("SELECT id FROM leases WHERE archived_at IS NULL", []);
            foreach ($leases as $lease) {
                $docs = Database::fetchAll("SELECT file_path FROM documents WHERE documentable_type = 'lease' AND documentable_id = ?", [$lease['id']]);
                foreach ($docs as $doc) {
                    $path = base_path($doc['file_path']);
                    if (file_exists($path)) unlink($path);
                }
                Database::execute("DELETE FROM documents WHERE documentable_type = 'lease' AND documentable_id = ?", [$lease['id']]);
            }
            Database::execute("DELETE FROM leases WHERE 1=1", []);
        }

        if ($resetTickets) {
            Database::execute("DELETE FROM ticket_comments WHERE 1=1", []);
            Database::execute("DELETE FROM tickets WHERE 1=1", []);
        }

        if ($resetTenants) {
            $tenantIds = Database::fetchAll(
                "SELECT u.id FROM users u WHERE u.role = 'tenant' AND u.archived_at IS NULL",
                []
            );
            $idList = array_column($tenantIds, 'id');
            if (!empty($idList)) {
                $idStr = implode(',', $idList);
                Database::execute("DELETE FROM property_tenant WHERE tenant_id IN ({$idStr})", []);
                Database::execute("DELETE FROM users WHERE id IN ({$idStr})", []);
            }
        }

        if ($resetProperties) {
            Database::execute("DELETE FROM property_tenant WHERE 1=1", []);
            Database::execute("DELETE FROM properties WHERE 1=1", []);
        }

        if ($resetStaff) {
            $staffIds = Database::fetchAll(
                "SELECT u.id FROM users u WHERE u.role IN ('landlord','property_manager','maintenance') AND u.id != ? AND u.archived_at IS NULL",
                [$admin['id']]
            );
            $idList = array_column($staffIds, 'id');
            if (!empty($idList)) {
                $idStr = implode(',', $idList);
                Database::execute("DELETE FROM company_user WHERE user_id IN ({$idStr})", []);
                Database::execute("DELETE FROM users WHERE id IN ({$idStr})", []);
            }
        }

        flash('success', 'Reset completed successfully.');
        redirect('/settings');
    }
}
