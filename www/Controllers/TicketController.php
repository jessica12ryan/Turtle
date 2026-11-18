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
                  LEFT JOIN properties p ON p.id = t.property_id 
                  JOIN users u ON u.id = t.tenant_id 
                  LEFT JOIN users a ON a.id = t.assigned_to 
                  WHERE 1=1{$archivedClause}";
        $params = [];

        if ($user['role'] === 'tenant') {
            $query .= " AND (t.tenant_id = ? OR t.property_id IN (SELECT property_id FROM property_tenant WHERE tenant_id = ? AND moved_out_at IS NULL)) AND t.status != 'closed'";
            $params[] = $auth->id();
            $params[] = $auth->id();
        } elseif ($user['role'] === 'admin') {
            // Admin sees all tickets
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
            $pmClause = $user['role'] === 'property_manager' ? ' AND property_manager_id = ?' : '';
            $pmParams = $pmClause ? [$auth->id()] : [];
            $propertyIds = Database::fetchAll(
                "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL{$pmClause}",
                $pmParams
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
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p 
                 JOIN users u ON u.id = p.landlord_id 
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL 
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
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
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
            [(int)$_POST['property_id'], Auth::instance()->id(), $_POST['subject'], $_POST['description'], $_POST['category'], $_POST['priority']]
        );

        $this->uploadTicketFiles($ticketId, null);

        log_activity('ticket.created', "Ticket '{$_POST['subject']}' created");
        flash('success', 'Ticket created successfully.');
        redirect('/tickets/' . $ticketId);
    }

    public function show(int $id): void
    {
        $ticket = Database::fetch(
            "SELECT t.*, p.name as property_name, p.landlord_id, p.company_id, p.property_manager_id,
                    u.name as tenant_name, a.name as assignee_name 
             FROM tickets t 
             JOIN properties p ON p.id = t.property_id 
             JOIN users u ON u.id = t.tenant_id 
             LEFT JOIN users a ON a.id = t.assigned_to 
             WHERE t.id = ?",
            [$id]
        );
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $user = Auth::instance()->user();
        if ($user['role'] === 'tenant') {
            if ($ticket['status'] === 'closed') {
                http_response_code(403);
                require base_path('www/Views/errors/403.php');
                return;
            }
            $assigned = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND property_id = ? AND moved_out_at IS NULL",
                [$user['id'], $ticket['property_id']]
            );
            if (!$assigned && $ticket['tenant_id'] != $user['id']) {
                http_response_code(403);
                require base_path('www/Views/errors/403.php');
                return;
            }
        }

        $comments = Database::fetchAll(
            "SELECT tc.*, u.name as user_name, u.role as user_role, u.secondary_roles FROM ticket_comments tc 
             JOIN users u ON u.id = tc.user_id 
             WHERE tc.ticket_id = ? ORDER BY tc.created_at ASC",
            [$id]
        );

        $staffUsers = [];
        if (!$ticket['archived_at'] && can('tickets.assign')) {
            $staffUsers = $this->assignableStaffForProperty($ticket);
        }

        $files = Database::fetchAll(
            "SELECT * FROM ticket_files WHERE ticket_id = ? ORDER BY created_at ASC",
            [$id]
        );

        $statuses = ['open', 'in_progress', 'awaiting_parts', 'awaiting_contractor', 'closed'];
        $categories = ['plumbing', 'electrical', 'hvac', 'appliances', 'structural', 'pest_control', 'general_repair', 'other'];
        $priorities = ['low', 'medium', 'high', 'emergency'];

        $view = new View();
        $view->layout('layouts/main', ['title' => $ticket['subject']]);
        $view->render('tickets/show', compact('ticket', 'comments', 'files', 'staffUsers', 'statuses', 'categories', 'priorities'));
    }

    public function assign(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        // The "Unassigned" option submits an empty string; normalize it to NULL.
        $assignedTo = $_POST['assigned_to'] ?? null;
        $assignedTo = ($assignedTo === '' || $assignedTo === null) ? null : (int)$assignedTo;

        $assignee = null;
        if ($assignedTo !== null) {
            $assignee = Database::fetch(
                "SELECT id, name, email, role, secondary_roles FROM users WHERE id = ? AND archived_at IS NULL",
                [$assignedTo]
            );
            if (!$assignee) {
                flash('error', 'Selected assignee is invalid.');
                redirectBack();
                return;
            }

            $property = Database::fetch(
                "SELECT company_id, property_manager_id FROM properties WHERE id = ?",
                [$ticket['property_id']]
            );
            if (!$property || !user_can_access_property($assignee, $property)) {
                flash('error', __('Selected assignee does not have access to this property.'));
                redirectBack();
                return;
            }
        }

        // Assigning moves the ticket to in progress; unassigning leaves the status untouched.
        $status = $assignedTo !== null ? 'in_progress' : $ticket['status'];
        Database::execute(
            "UPDATE tickets SET assigned_to = ?, status = ?, updated_at = NOW() WHERE id = ?",
            [$assignedTo, $status, $id]
        );

        $user = Auth::instance()->user();
        if ($assignee) {
            $commentBody = ticket_assignment_comment_body($user['name'], $assignee['name']);
            Mailer::sendTemplate(
                $assignee['email'],
                'Ticket Assigned: ' . $ticket['subject'],
                'Hello ' . h($assignee['name']) . ',',
                'A ticket has been assigned to you.<br><br><strong>Subject:</strong> ' . h($ticket['subject']) . '<br><strong>Priority:</strong> ' . ucfirst($ticket['priority']),
                \site_url() . '/tickets/' . $id,
                'View Ticket'
            );
            notifyInApp((int)$assignedTo, 'ticket_assigned', 'A ticket has been assigned to you: ' . $ticket['subject']);
        } else {
            $commentBody = ticket_assignment_comment_body($user['name'], null);
        }

        Database::insert(
            "INSERT INTO ticket_comments (ticket_id, user_id, body, is_system, created_at) VALUES (?, ?, ?, 1, NOW())",
            [$id, $user['id'], $commentBody]
        );

        log_activity('ticket.assigned', "Ticket #{$id} assigned");
        flash('success', 'Ticket assigned successfully.');
        redirect('/tickets/' . $id);
    }

    public function restore(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }

        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NOT NULL", [$id]);
        if (!$ticket) {
            flash('error', __('Ticket not found or already restored.'));
            redirect('/tickets?show_archived=1');
            return;
        }

        $conflicts = $this->restoreConflicts($ticket);
        if ($conflicts['property_missing'] || $conflicts['assignee_invalid']) {
            redirect('/tickets/' . $id . '/restore-conflict');
            return;
        }

        $this->performRestore($id, (int)$ticket['property_id'], null, null);
    }

    public function restoreConflict(int $id): void
    {
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NOT NULL", [$id]);
        if (!$ticket) {
            flash('error', __('Ticket not found or already restored.'));
            redirect('/tickets?show_archived=1');
            return;
        }

        $conflicts = $this->restoreConflicts($ticket);
        if (!$conflicts['property_missing'] && !$conflicts['assignee_invalid']) {
            redirect('/tickets?show_archived=1');
            return;
        }

        $properties = $this->restoreProperties();
        $managersByProperty = [];
        foreach ($properties as $property) {
            $managersByProperty[(string)$property['id']] = array_map(
                fn($u) => (string)$u['id'],
                $this->assignableStaffForProperty($property)
            );
        }

        $currentProperty = $conflicts['property_missing']
            ? null
            : Database::fetch(
                "SELECT id, name, company_id, property_manager_id FROM properties WHERE id = ?",
                [$ticket['property_id']]
            );
        $initialManagers = $currentProperty ? $this->assignableStaffForProperty($currentProperty) : [];

        $managerNames = [];
        foreach (Database::fetchAll("SELECT id, name, email FROM users WHERE archived_at IS NULL", []) as $user) {
            $managerNames[(string)$user['id']] = $user['name'] . ' (' . $user['email'] . ')';
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => __('Restore Ticket')]);
        $view->render('tickets/restore_conflict', compact(
            'ticket',
            'conflicts',
            'properties',
            'managersByProperty',
            'initialManagers',
            'managerNames'
        ));
    }

    public function restoreResolve(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }

        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NOT NULL", [$id]);
        if (!$ticket) {
            flash('error', __('Ticket not found or already restored.'));
            redirect('/tickets?show_archived=1');
            return;
        }

        $conflicts = $this->restoreConflicts($ticket);
        $errorRedirect = '/tickets/' . $id . '/restore-conflict';
        $propertyId = (int)$ticket['property_id'];

        if ($conflicts['property_missing']) {
            $candidate = (string)($_POST['property_id'] ?? '');
            $allowed = array_map(fn($p) => (string)$p['id'], $this->restoreProperties());
            if ($candidate === '' || !in_array($candidate, $allowed, true)) {
                flash('error', __('Selected property is invalid.'));
                redirect($errorRedirect);
                return;
            }
            $propertyId = (int)$candidate;
        }

        $assignmentChanged = $conflicts['assignee_invalid'];
        $assigneeId = $conflicts['assignee_invalid'] ? null : (!empty($ticket['assigned_to']) ? (int)$ticket['assigned_to'] : null);
        $assigneeName = $conflicts['assignee_invalid'] ? null : ($conflicts['assignee_name'] ?? null);
        if ($conflicts['assignee_invalid']) {
            $value = (string)($_POST['assigned_to'] ?? '');
            if ($value === '' || $value === 'unassign') {
                $assigneeId = null;
                $assigneeName = null;
            } else {
                $property = Database::fetch(
                    "SELECT id, name, company_id, property_manager_id FROM properties WHERE id = ? AND archived_at IS NULL",
                    [$propertyId]
                );
                $allowed = array_map(fn($u) => (string)$u['id'], $this->assignableStaffForProperty($property));
                if (!ctype_digit($value) || !in_array($value, $allowed, true)) {
                    flash('error', __('Selected assignee does not have access to this property.'));
                    redirect($errorRedirect);
                    return;
                }
                $assigneeId = (int)$value;
                $row = Database::fetch("SELECT name FROM users WHERE id = ?", [$assigneeId]);
                $assigneeName = $row['name'] ?? 'Unknown';
            }
        }

        $this->performRestore($id, $propertyId, $assigneeId, $assigneeName, $assignmentChanged);
    }

    public function status(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
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
                \site_url() . '/tickets/' . $id,
                'View Ticket'
            );
            notifyInApp((int)$ticket['tenant_id'], 'ticket_status_updated',
                'Ticket status updated: ' . $ticket['subject'] . ' — ' . ucfirst(str_replace('_', ' ', $_POST['status'])));
        }

        log_activity('ticket.status_updated', "Ticket #{$id} status updated");
        flash('success', 'Ticket status updated.');
        redirect('/tickets/' . $id);
    }

    public function comment(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $user = Auth::instance()->user();
        if ($user['role'] === 'tenant') {
            if ($ticket['status'] === 'closed') {
                http_response_code(403);
                require base_path('www/Views/errors/403.php');
                return;
            }
            $assigned = Database::fetch(
                "SELECT 1 FROM property_tenant WHERE tenant_id = ? AND property_id = ? AND moved_out_at IS NULL",
                [$user['id'], $ticket['property_id']]
            );
            if (!$assigned && $ticket['tenant_id'] != $user['id']) {
                http_response_code(403);
                require base_path('www/Views/errors/403.php');
                return;
            }
        }

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

        log_activity('ticket.comment_added', "Comment added to ticket #{$id}");
        flash('success', 'Comment added successfully.');
        redirect('/tickets/' . $id);
    }

    public function downloadFile(int $ticketId, int $fileId): void
    {
        $auth = Auth::instance();
        $user = $auth->user();

        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        // IDOR check: verify user has access to this ticket
        if ($user['role'] === 'tenant') {
            if ((int)$ticket['tenant_id'] !== (int)$user['id']) {
                http_response_code(403); require base_path('www/Views/errors/404.php'); return;
            }
        } elseif (!in_array($user['role'], ['admin', 'landlord', 'property_manager', 'maintenance'])) {
            http_response_code(403); require base_path('www/Views/errors/404.php'); return;
        }

        $file = Database::fetch("SELECT * FROM ticket_files WHERE id = ? AND ticket_id = ?", [$fileId, $ticketId]);
        if (!$file) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $fullPath = base_path($file['file_path']);
        if (!file_exists($fullPath)) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        header('Content-Type: ' . ($file['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . sanitize_filename($file['original_name']) . '"');
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
                return;
            }
        }

        $userId = Auth::instance()->id();
        foreach ($_FILES['attachments']['error'] as $i => $error) {
            if ($error !== UPLOAD_ERR_OK) continue;

            $name = $_FILES['attachments']['name'][$i];
            $tmpName = $_FILES['attachments']['tmp_name'][$i];
            $size = $_FILES['attachments']['size'][$i];

            if ($name === '' || $tmpName === '') continue;

            $allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) { continue; }
            $filename = uniqid() . '.' . $ext;
            $destPath = $uploadDir . '/' . $filename;

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedType = $finfo->file($tmpName);
            $allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/plain'];
            if (!in_array($detectedType, $allowedMimes, true)) { continue; }

            if (move_uploaded_file($tmpName, $destPath)) {
                Database::insert(
                    "INSERT INTO ticket_files (ticket_id, comment_id, file_path, original_name, size, mime_type, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$ticketId, $commentId, 'storage/uploads/ticket_files/' . $ticketId . '/' . $filename, $name, $size, $detectedType, $userId]
                );
            }
        }
    }

    public function archive(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute("UPDATE tickets SET archived_at = NOW(), status = 'closed' WHERE id = ?", [$id]);
        log_activity('ticket.archived', "Ticket #{$id} archived");
        flash('success', 'Ticket archived successfully.');
        redirect('/tickets');
    }

    public function delete(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
        $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$id]);
        if (!$ticket) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        Database::execute("DELETE FROM ticket_files WHERE ticket_id = ?", [$id]);
        Database::execute("DELETE FROM ticket_comments WHERE ticket_id = ?", [$id]);
        Database::execute("DELETE FROM tickets WHERE id = ?", [$id]);
        log_activity('ticket.deleted', "Ticket #{$ticket['subject']} permanently deleted");
        flash('success', 'Ticket permanently deleted.');
        redirect('/tickets');
    }

    private function assignableStaffForProperty(array $property): array
    {
        $staffUsers = Database::fetchAll(
            "SELECT u.* FROM users u 
             WHERE u.archived_at IS NULL 
               AND u.role IN ('admin','landlord','property_manager','maintenance')
             ORDER BY u.name"
        );

        $staffIds = array_column($staffUsers, 'id');
        $companyMap = [];
        if (!empty($staffIds)) {
            $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
            $companyRows = Database::fetchAll(
                "SELECT user_id, company_id FROM company_user WHERE user_id IN ({$placeholders})",
                $staffIds
            );
            foreach ($companyRows as $row) {
                $companyMap[(int)$row['user_id']][] = (int)$row['company_id'];
            }
        }

        return array_values(array_filter(
            $staffUsers,
            fn($u) => user_can_access_property($u, $property, $companyMap[(int)$u['id']] ?? [])
        ));
    }

    private function restoreProperties(): array
    {
        $actor = Auth::instance()->user();
        $properties = Database::fetchAll(
            "SELECT id, name, company_id, property_manager_id FROM properties WHERE archived_at IS NULL ORDER BY name"
        );

        return array_values(array_filter(
            $properties,
            fn($p) => user_can_access_property($actor, $p)
        ));
    }

    private function restoreConflicts(array $ticket): array
    {
        $property = Database::fetch(
            "SELECT id, name, company_id, property_manager_id FROM properties WHERE id = ? AND archived_at IS NULL",
            [$ticket['property_id']]
        );

        $assignee = null;
        if (!empty($ticket['assigned_to'])) {
            $assignee = Database::fetch(
                "SELECT id, name FROM users WHERE id = ? AND archived_at IS NULL",
                [$ticket['assigned_to']]
            );
        }

        return [
            'property_missing' => $property === null,
            'assignee_invalid' => !empty($ticket['assigned_to']) && $assignee === null,
            'assignee_name' => $assignee['name'] ?? null,
        ];
    }

    private function performRestore(
        int $id,
        int $propertyId,
        ?int $assigneeId,
        ?string $assigneeName,
        bool $assignmentChanged = false
    ): void {
        $actor = Auth::instance()->user();

        try {
            Database::beginTransaction();
            Database::execute(
                "UPDATE tickets SET property_id = ?, assigned_to = ?, archived_at = NULL, updated_at = NOW() WHERE id = ?",
                [$propertyId, $assigneeId, $id]
            );
            if ($assignmentChanged) {
                Database::insert(
                    "INSERT INTO ticket_comments (ticket_id, user_id, body, is_system, created_at) VALUES (?, ?, ?, 1, NOW())",
                    [$id, $actor['id'], ticket_assignment_comment_body($actor['name'], $assigneeName)]
                );
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            error_log('ticket restore failed: ' . $e->getMessage());
            flash('error', __('Could not restore this ticket. No changes were made.'));
            redirect('/tickets?show_archived=1');
            return;
        }

        log_activity('ticket.restored', "Ticket #{$id} restored");
        flash('success', __('Ticket restored successfully.'));
        redirect('/tickets/' . $id);
    }
}
