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
            ];
        }

        // Tenant move-out dates
        $moveOuts = Database::fetchAll(
            "SELECT pt.moved_out_at, u.name as tenant_name, p.name as property_name, pt.id
             FROM property_tenant pt
             JOIN users u ON u.id = pt.tenant_id
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.moved_out_at IS NOT NULL"
        );
        foreach ($moveOuts as $m) {
            $date = date('Y-m-d', strtotime($m['moved_out_at']));
            $events[] = [
                'id' => 'moveout-' . $m['id'],
                'title' => 'Move Out: ' . $m['tenant_name'] . ' (' . $m['property_name'] . ')',
                'start' => $date,
                'allDay' => true,
                'className' => 'bg-red-100 text-red-800 border-red-300',
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
            ];
        }

        echo json_encode($events);
    }
}
