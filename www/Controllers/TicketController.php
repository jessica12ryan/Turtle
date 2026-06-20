<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Core\Validator;
use App\Core\Mailer;

class TicketController
{
    public function index(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();
        $query = "SELECT t.*, p.name as property_name, u.name as tenant_name, a.name as assignee_name 
                  FROM tickets t 
                  JOIN properties p ON p.id = t.property_id 
                  JOIN users u ON u.id = t.tenant_id 
                  LEFT JOIN users a ON a.id = t.assigned_to 
                  WHERE t.archived_at IS NULL";
        $params = [];

        if ($user['role'] === 'tenant') {
            $query .= " AND t.tenant_id = ?";
            $params[] = $auth->id();
        } elseif ($user['role'] === 'maintenance') {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
            $propertyIds = Database::fetchAll(
                "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL"
            );
            $propertyIdList = implode(',', array_column($propertyIds, 'id')) ?: '0';
            $query .= " AND (t.assigned_to = ? OR t.property_id IN ({$propertyIdList}))";
            $params[] = $auth->id();
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
            $propertyIds = Database::fetchAll(
                "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL"
            );
            $propertyIdList = implode(',', array_column($propertyIds, 'id')) ?: '0';
            $query .= " AND t.property_id IN ({$propertyIdList})";
        }

        $query .= " ORDER BY t.created_at DESC";

        $tickets = Database::fetchAll($query, $params);

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Tickets']);
        $view->render('tickets/index', compact('tickets'));
    }

    public function create(): void
    {
        $properties = Database::fetchAll(
            "SELECT p.*, c.name as company_name FROM properties p 
             JOIN property_tenant pt ON pt.property_id = p.id 
             JOIN companies c ON c.id = p.company_id 
             WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
            [Auth::instance()->id()]
        );

        $categories = ['plumbing', 'electrical', 'hvac', 'appliances', 'structural', 'pest_control', 'general_repair', 'other'];
        $priorities = ['low', 'medium', 'high', 'emergency'];

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Create Ticket']);
        $view->render('tickets/create', compact('properties', 'categories', 'priorities'));
    }

    public function store(): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, [
            'property_id' => 'required|exists:properties,id',
            'subject' => 'required|max:255',
            'description' => 'required',
            'category' => 'required',
            'priority' => 'required',
        ])) {
            $_SESSION['_errors'] = $validator->errors();
            $_SESSION['_old'] = $_POST;
            redirect('/tickets/create');
        }

        $ticketId = Database::insert(
            "INSERT INTO tickets (property_id, tenant_id, subject, description, category, status, priority, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'open', ?, NOW(), NOW())",
            [$_POST['property_id'], Auth::instance()->id(), $_POST['subject'], $_POST['description'], $_POST['category'], $_POST['priority']]
        );

        flash('success', 'Ticket created successfully.');
        redirect('/tickets/' . $ticketId);
    }

    public function show(int $id): void
    {
        $ticket = Database::fetch(
            "SELECT t.*, p.name as property_name, p.company_id, u.name as tenant_name, a.name as assignee_name 
             FROM tickets t 
             JOIN properties p ON p.id = t.property_id 
             JOIN users u ON u.id = t.tenant_id 
             LEFT JOIN users a ON a.id = t.assigned_to 
             WHERE t.id = ? AND t.archived_at IS NULL",
            [$id]
        );
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $comments = Database::fetchAll(
            "SELECT tc.*, u.name as user_name, u.role as user_role FROM ticket_comments tc 
             JOIN users u ON u.id = tc.user_id 
             WHERE tc.ticket_id = ? ORDER BY tc.created_at ASC",
            [$id]
        );

        $staffUsers = [];
        if (in_array(Auth::instance()->user()['role'], ['landlord', 'property_manager'])) {
            $staffUsers = Database::fetchAll(
                "SELECT u.* FROM users u 
                 JOIN company_user cu ON cu.user_id = u.id 
                 WHERE cu.company_id = ? AND u.archived_at IS NULL AND u.role IN ('landlord','property_manager','maintenance')
                 ORDER BY u.name",
                [$ticket['company_id']]
            );
        }

        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $categories = ['plumbing', 'electrical', 'hvac', 'appliances', 'structural', 'pest_control', 'general_repair', 'other'];
        $priorities = ['low', 'medium', 'high', 'emergency'];

        $view = new View();
        $view->layout('layouts/main', ['title' => $ticket['subject']]);
        $view->render('tickets/show', compact('ticket', 'comments', 'staffUsers', 'statuses', 'categories', 'priorities'));
    }

    public function assign(int $id): void
    {
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute(
            "UPDATE tickets SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?",
            [$_POST['assigned_to'] ?? null, $id]
        );

        if (!empty($_POST['assigned_to'])) {
            $assignee = Database::fetch("SELECT name, email FROM users WHERE id = ?", [$_POST['assigned_to']]);
            if ($assignee) {
                Mailer::sendTemplate(
                    $assignee['email'],
                    'Ticket Assigned: ' . $ticket['subject'],
                    'Hello ' . h($assignee['name']) . ',',
                    'A ticket has been assigned to you.<br><br><strong>Subject:</strong> ' . h($ticket['subject']) . '<br><strong>Priority:</strong> ' . ucfirst($ticket['priority']),
                    'http://' . $_SERVER['HTTP_HOST'] . '/tickets/' . $id,
                    'View Ticket'
                );
            }
        }

        flash('success', 'Ticket assigned successfully.');
        redirect('/tickets/' . $id);
    }

    public function status(int $id): void
    {
        Database::execute(
            "UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['status'], $id]
        );

        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$id]);
        if ($ticket) {
            $tenant = Database::fetch("SELECT name, email FROM users WHERE id = ?", [$ticket['tenant_id']]);
            if ($tenant) {
                Mailer::sendTemplate(
                    $tenant['email'],
                    'Ticket Update: ' . $ticket['subject'],
                    'Hello ' . h($tenant['name']) . ',',
                    'Your ticket status has been updated.<br><br><strong>Subject:</strong> ' . h($ticket['subject']) . '<br><strong>New Status:</strong> ' . ucfirst(str_replace('_', ' ', $ticket['status'])),
                    'http://' . $_SERVER['HTTP_HOST'] . '/tickets/' . $id,
                    'View Ticket'
                );
            }
        }

        flash('success', 'Ticket status updated.');
        redirect('/tickets/' . $id);
    }

    public function comment(int $id): void
    {
        $validator = new Validator();
        if (!$validator->validate($_POST, ['body' => 'required'])) {
            flash('error', 'Comment cannot be empty.');
            redirect('/tickets/' . $id);
        }

        $isInternal = (!empty($_POST['is_internal']) && in_array(Auth::instance()->user()['role'], ['landlord', 'property_manager', 'maintenance'])) ? 1 : 0;

        Database::insert(
            "INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$id, Auth::instance()->id(), $_POST['body'], $isInternal]
        );

        flash('success', 'Comment added successfully.');
        redirect('/tickets/' . $id);
    }
}
