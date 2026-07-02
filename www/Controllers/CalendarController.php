<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class CalendarController
{
    public function index(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => 'Calendar']);
        $view->render('calendar/index');
    }

    public function events(): void
    {
        header('Content-Type: application/json');

        $user = Auth::instance()->user();
        $events = [];

        // Determine accessible property IDs for payment filtering
        $accessiblePropertyIds = [];
        if ($user['role'] === 'admin') {
            $rows = Database::fetchAll("SELECT id FROM properties WHERE archived_at IS NULL");
            $accessiblePropertyIds = array_column($rows, 'id');
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$user['id']]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
            $pmClause = $user['role'] === 'property_manager' ? ' AND property_manager_id = ?' : '';
            $params = $pmClause ? [$user['id']] : [];
            $rows = Database::fetchAll(
                "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL{$pmClause}",
                $params
            );
            $accessiblePropertyIds = array_column($rows, 'id');
        }
        $accessibleIdList = implode(',', $accessiblePropertyIds ?: ['0']);

        // Tenant move-in dates
        $moveIns = Database::fetchAll(
            "SELECT pt.lease_start, u.name as tenant_name, p.name as property_name, pt.id
             FROM property_tenant pt
             JOIN users u ON u.id = pt.tenant_id
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.lease_start IS NOT NULL AND u.archived_at IS NULL"
        );
        foreach ($moveIns as $m) {
            $events[] = [
                'id' => 'movein-' . $m['id'],
                'title' => 'Move In: ' . $m['tenant_name'] . ' (' . $m['property_name'] . ')',
                'start' => $m['lease_start'],
                'allDay' => true,
                'className' => 'bg-green-100 text-green-800 border-green-300',
                'type' => 'movein',
            ];
        }

        // Tenant scheduled move-out dates
        $moveOuts = Database::fetchAll(
            "SELECT pt.move_out_date, u.name as tenant_name, p.name as property_name, pt.id
             FROM property_tenant pt
             JOIN users u ON u.id = pt.tenant_id
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.move_out_date IS NOT NULL AND pt.moved_out_at IS NULL"
        );
        foreach ($moveOuts as $m) {
            $events[] = [
                'id' => 'moveout-' . $m['id'],
                'title' => __('Scheduled Move Out') . ': ' . $m['tenant_name'] . ' (' . $m['property_name'] . ')',
                'start' => $m['move_out_date'],
                'allDay' => true,
                'className' => 'bg-orange-100 text-orange-800 border-orange-300',
                'type' => 'moveout',
            ];
        }

        // Lease end dates
        $leaseEnds = Database::fetchAll(
            "SELECT pt.lease_end, u.name as tenant_name, p.name as property_name, pt.id
             FROM property_tenant pt
             JOIN users u ON u.id = pt.tenant_id
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.lease_end IS NOT NULL AND u.archived_at IS NULL"
        );
        foreach ($leaseEnds as $l) {
            $events[] = [
                'id' => 'leaseend-' . $l['id'],
                'title' => 'Lease Ends: ' . $l['tenant_name'] . ' (' . $l['property_name'] . ')',
                'start' => $l['lease_end'],
                'allDay' => true,
                'className' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                'type' => 'leaseend',
            ];
        }

        // Rent payments (filtered by property access)
        $payments = Database::fetchAll(
            "SELECT p.amount, p.payment_date, u.name as tenant_name, prop.name as property_name, p.id, pt.id as pt_id
             FROM payments p
             JOIN property_tenant pt ON pt.id = p.property_tenant_id
             JOIN users u ON u.id = pt.tenant_id
             JOIN properties prop ON prop.id = pt.property_id
             WHERE p.archived_at IS NULL AND p.is_security_deposit = 0 AND prop.id IN ({$accessibleIdList})"
        );
        foreach ($payments as $pmt) {
            $events[] = [
                'id' => 'payment-' . $pmt['id'],
                'title' => '$' . number_format($pmt['amount'], 2) . ' — ' . $pmt['tenant_name'] . ' (' . $pmt['property_name'] . ')',
                'start' => $pmt['payment_date'],
                'allDay' => true,
                'className' => 'bg-blue-100 text-blue-800 border-blue-300',
                'type' => 'payment',
            ];
        }

        // Security deposit payments (filtered by property access)
        $deposits = Database::fetchAll(
            "SELECT p.amount, p.payment_date, u.name as tenant_name, prop.name as property_name, p.id, pt.id as pt_id
             FROM payments p
             JOIN property_tenant pt ON pt.id = p.property_tenant_id
             JOIN users u ON u.id = pt.tenant_id
             JOIN properties prop ON prop.id = pt.property_id
             WHERE p.archived_at IS NULL AND p.is_security_deposit = 1 AND prop.id IN ({$accessibleIdList})"
        );
        foreach ($deposits as $d) {
            $events[] = [
                'id' => 'deposit-' . $d['id'],
                'title' => 'Security Deposit: $' . number_format($d['amount'], 2) . ' — ' . $d['tenant_name'] . ' (' . $d['property_name'] . ')',
                'start' => $d['payment_date'],
                'allDay' => true,
                'className' => 'bg-purple-100 text-purple-800 border-purple-300',
                'type' => 'deposit',
            ];
        }

        echo json_encode($events);
    }
}
