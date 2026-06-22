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
        $showArchived = !empty($_GET['show_archived']);
        $archivedClause = $showArchived ? '' : ' AND t.archived_at IS NULL';

        $query = "SELECT t.*, p.name as property_name, u.name as tenant_name, a.name as assignee_name,
                         t.archived_at
                  FROM tickets t 
                  JOIN properties p ON p.id = t.property_id 
                  JOIN users u ON u.id = t.tenant_id 
                  LEFT JOIN users a ON a.id = t.assigned_to 
                  WHERE 1=1{$archivedClause}";
        $params = [];

        if ($user['role'] === 'tenant') {
            $query .= " AND t.tenant_id = ?";
            $params[] = $auth->id();
        } elseif ($user['role'] === 'admin') {
            // Admin sees all tickets
        } elseif ($user['role'] === 'maintenance') {
            $userIds = Database::fetchAll(
                "SELECT landlord_id FROM properties WHERE archived_at IS NULL
                 AND landlord_id IN (SELECT u.id FROM users u JOIN company_user cu ON cu.user_id = u.id WHERE cu.user_id = ?)
                 UNION SELECT ?",
                [$auth->id(), $auth->id()]
            );
            $userIdList = implode(',', array_column($userIds, 'landlord_id')) ?: '0';
            $propertyIds = Database::fetchAll(
                "SELECT id FROM properties WHERE landlord_id IN ({$userIdList}) AND archived_at IS NULL"
            );
            $propertyIdList = implode(',', array_column($propertyIds, 'id')) ?: '0';
            $query .= " AND (t.assigned_to = ? OR t.property_id IN ({$propertyIdList}))";
            $params[] = $auth->id();
        } else {
            $userIds = Database::fetchAll(
                "SELECT landlord_id FROM properties WHERE archived_at IS NULL
                 AND landlord_id IN (SELECT u.id FROM users u JOIN company_user cu ON cu.user_id = u.id WHERE cu.user_id = ?)
                 UNION SELECT ?",
                [$auth->id(), $auth->id()]
            );
            $userIdList = implode(',', array_column($userIds, 'landlord_id')) ?: '0';
            $propertyIds = Database::fetchAll(
                "SELECT id FROM properties WHERE landlord_id IN ({$userIdList}) AND archived_at IS NULL"
            );
            $propertyIdList = implode(',', array_column($propertyIds, 'id')) ?: '0';
            $query .= " AND t.property_id IN ({$propertyIdList})";
        }

        $query .= " ORDER BY t.archived_at IS NULL DESC, t.created_at DESC";

        $tickets = Database::fetchAll($query, $params);

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Tickets']);
        $view->render('tickets/index', compact('tickets', 'showArchived'));
    }

    public function create(): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        if ($user['role'] === 'tenant') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id
                 JOIN property_tenant pt ON pt.property_id = p.id 
                 WHERE pt.tenant_id = ? AND pt.moved_out_at IS NULL AND p.archived_at IS NULL",
                [$auth->id()]
            );
        } elseif ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        } else {
            $userIds = Database::fetchAll(
                "SELECT landlord_id FROM properties WHERE archived_at IS NULL
                 AND landlord_id IN (SELECT u.id FROM users u JOIN company_user cu ON cu.user_id = u.id WHERE cu.user_id = ?)
                 UNION SELECT ?",
                [$auth->id(), $auth->id()]
            );
            $userIdList = implode(',', array_column($userIds, 'landlord_id')) ?: '0';
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.landlord_id IN ({$userIdList}) AND p.archived_at IS NULL 
                 ORDER BY p.name"
            );
        }

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

        $this->uploadTicketFiles($ticketId, null);

        flash('success', 'Ticket created successfully.');
        redirect('/tickets/' . $ticketId);
    }

    public function show(int $id): void
    {
        $ticket = Database::fetch(
            "SELECT t.*, p.name as property_name, p.landlord_id, u.name as tenant_name, a.name as assignee_name 
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
        if (in_array(Auth::instance()->user()['role'], ['admin', 'landlord', 'property_manager'])) {
            $staffUsers = Database::fetchAll(
                "SELECT u.* FROM users u 
                 WHERE u.archived_at IS NULL 
                   AND u.role IN ('admin','landlord','property_manager','maintenance')
                 ORDER BY u.name"
            );
        }

        $files = Database::fetchAll(
            "SELECT * FROM ticket_files WHERE ticket_id = ? ORDER BY created_at ASC",
            [$id]
        );

        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $categories = ['plumbing', 'electrical', 'hvac', 'appliances', 'structural', 'pest_control', 'general_repair', 'other'];
        $priorities = ['low', 'medium', 'high', 'emergency'];

        $view = new View();
        $view->layout('layouts/main', ['title' => $ticket['subject']]);
        $view->render('tickets/show', compact('ticket', 'comments', 'files', 'staffUsers', 'statuses', 'categories', 'priorities'));
    }

    public function assign(int $id): void
    {
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $assignedTo = $_POST['assigned_to'] ?? null;

        Database::execute(
            "UPDATE tickets SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?",
            [$assignedTo, $id]
        );

        $user = Auth::instance()->user();
        if ($assignedTo) {
            $assignee = Database::fetch("SELECT name, email FROM users WHERE id = ?", [$assignedTo]);
            $assigneeName = $assignee ? $assignee['name'] : 'Unknown';
            $commentBody = $user['name'] . ' assigned ticket to ' . $assigneeName;
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
        } else {
            $commentBody = $user['name'] . ' assigned ticket to Unassigned';
        }

        Database::insert(
            "INSERT INTO ticket_comments (ticket_id, user_id, body, is_system, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$id, $user['id'], $commentBody]
        );

        flash('success', 'Ticket assigned successfully.');
        redirect('/tickets/' . $id);
    }

    public function restore(int $id): void
    {
        Database::execute("UPDATE tickets SET archived_at = NULL WHERE id = ?", [$id]);
        flash('success', 'Ticket restored successfully.');
        redirect('/tickets');
    }

    public function status(int $id): void
    {
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $oldStatus = str_replace('_', ' ', $ticket['status']);
        $newStatus = str_replace('_', ' ', $_POST['status']);

        Database::execute(
            "UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['status'], $id]
        );

        $user = Auth::instance()->user();
        Database::insert(
            "INSERT INTO ticket_comments (ticket_id, user_id, body, is_system, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$id, $user['id'], $user['name'] . ' changed ticket status from ' . $oldStatus . ' to ' . $newStatus]
        );

        $tenant = Database::fetch("SELECT name, email FROM users WHERE id = ?", [$ticket['tenant_id']]);
        if ($tenant) {
            Mailer::sendTemplate(
                $tenant['email'],
                'Ticket Update: ' . $ticket['subject'],
                'Hello ' . h($tenant['name']) . ',',
                'Your ticket status has been updated.<br><br><strong>Subject:</strong> ' . h($ticket['subject']) . '<br><strong>New Status:</strong> ' . ucfirst(str_replace('_', ' ', $_POST['status'])),
                'http://' . $_SERVER['HTTP_HOST'] . '/tickets/' . $id,
                'View Ticket'
            );
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

        $isInternal = (!empty($_POST['is_internal']) && Auth::instance()->isStaff()) ? 1 : 0;

        $commentId = Database::insert(
            "INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$id, Auth::instance()->id(), $_POST['body'], $isInternal]
        );

        $this->uploadTicketFiles((int)$id, $commentId);

        flash('success', 'Comment added successfully.');
        redirect('/tickets/' . $id);
    }

    public function downloadFile(int $ticketId, int $fileId): void
    {
        $file = Database::fetch("SELECT * FROM ticket_files WHERE id = ? AND ticket_id = ?", [$fileId, $ticketId]);
        if (!$file) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $fullPath = base_path($file['file_path']);
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }

        header('Content-Type: ' . ($file['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    private function uploadTicketFiles(int $ticketId, ?int $commentId): void
    {
        if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'])) {
            return;
        }

        $hasFiles = false;
        foreach ($_FILES['attachments']['error'] as $err) {
            if ($err === UPLOAD_ERR_OK) {
                $hasFiles = true;
                break;
            }
        }
        if (!$hasFiles) return;

        $uploadDir = base_path('storage/uploads/ticket_files/' . $ticketId);
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $uploadDir = sys_get_temp_dir() . '/turtle_ticket_files/' . $ticketId;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
            }
        }

        $userId = Auth::instance()->id();
        foreach ($_FILES['attachments']['error'] as $i => $error) {
            if ($error !== UPLOAD_ERR_OK) continue;

            $name = $_FILES['attachments']['name'][$i];
            $tmpName = $_FILES['attachments']['tmp_name'][$i];
            $size = $_FILES['attachments']['size'][$i];
            $type = $_FILES['attachments']['type'][$i];

            if ($name === '' || $tmpName === '') continue;

            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $destPath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($tmpName, $destPath)) {
                Database::insert(
                    "INSERT INTO ticket_files (ticket_id, comment_id, file_path, original_name, size, mime_type, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$ticketId, $commentId, 'storage/uploads/ticket_files/' . $ticketId . '/' . $filename, $name, $size, $type, $userId]
                );
            }
        }
    }
}
