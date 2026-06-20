<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class DashboardController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        if ($user['role'] === 'tenant') {
            $properties = Database::fetchAll(
                "SELECT p.*, c.name as company_name FROM properties p 
                 JOIN property_tenant pt ON pt.property_id = p.id 
                 JOIN companies c ON c.id = p.company_id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$auth->id()]
            );

            $tickets = Database::fetchAll(
                "SELECT t.*, p.name as property_name FROM tickets t 
                 JOIN properties p ON p.id = t.property_id 
                 WHERE t.tenant_id = ? AND t.archived_at IS NULL 
                 ORDER BY t.created_at DESC LIMIT 5",
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

            $view = new View();
            $view->layout('layouts/main', ['title' => 'Dashboard']);
            $view->render('dashboard/tenant', compact('properties', 'tickets', 'leases'));
            return;
        }

        // Staff dashboard
        $isAdmin = $user['role'] === 'admin';
        $companyIds = Database::fetchAll(
            "SELECT company_id FROM company_user WHERE user_id = ?",
            [$auth->id()]
        );
        $companyIds = array_column($companyIds, 'company_id');
        if ($isAdmin) {
            $companyIdList = 'SELECT id FROM companies WHERE archived_at IS NULL';
        } else {
            $companyIdList = implode(',', array_map('intval', $companyIds)) ?: '0';
        }

        $companies = Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM properties WHERE company_id = c.id AND archived_at IS NULL) as properties_count 
             FROM companies c WHERE c.id IN ({$companyIdList}) AND c.archived_at IS NULL"
        );

        $propertyIds = Database::fetchAll(
            "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL"
        );
        $propertyIdList = implode(',', array_column($propertyIds, 'id')) ?: '0';

        $propertiesCount = count($propertyIds);

        $activeTenants = Database::fetch(
            "SELECT COUNT(*) as count FROM property_tenant 
             WHERE property_id IN ({$propertyIdList}) AND moved_out_at IS NULL"
        )['count'] ?? 0;

        $openTickets = Database::fetch(
            "SELECT COUNT(*) as count FROM tickets 
             WHERE property_id IN ({$propertyIdList}) AND status IN ('open','in_progress') AND archived_at IS NULL"
        )['count'] ?? 0;

        $recentTickets = Database::fetchAll(
            "SELECT t.*, p.name as property_name, u.name as tenant_name FROM tickets t 
             JOIN properties p ON p.id = t.property_id 
             JOIN users u ON u.id = t.tenant_id 
             WHERE t.property_id IN ({$propertyIdList}) AND t.archived_at IS NULL 
             ORDER BY t.created_at DESC LIMIT 5"
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Dashboard']);
        $view->render('dashboard/staff', compact('companies', 'propertiesCount', 'activeTenants', 'openTickets', 'recentTickets'));
    }
}
