<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class ApplicationController
{
    public function create(): void
    {
        $settings = $this->getSettings();
        if ($settings['enabled'] !== '1') {
            $view = new View();
            $view->layout('layouts/guest', ['title' => __('Tenancy Application')]);
            $view->render('applications/disabled');
            return;
        }

        $properties = Database::fetchAll(
            "SELECT id, name, address, city, province FROM properties WHERE archived_at IS NULL ORDER BY name"
        );

        $view = new View();
        $view->layout('layouts/guest', ['title' => __('Tenancy Application')]);
        $view->render('applications/create', [
            'properties' => $properties,
            'notes' => $settings['notes'] ?? '',
        ]);
    }

    public function store(): void
    {
        try {
            $settings = $this->getSettings();
            if ($settings['enabled'] !== '1') {
                $view = new View();
                $view->layout('layouts/guest', ['title' => __('Tenancy Application')]);
                $view->render('applications/disabled');
                return;
            }

            $this->ensureTable();

            if (empty($_POST['_csrf']) || $_POST['_csrf'] !== ($_SESSION['_csrf_token'] ?? '')) {
                flash('error', 'Invalid form token. Please try again.');
                redirect('/applications/create');
            }

            $propertyId = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
            $data = $this->buildData();

            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                error_log('Application submission failed: json_encode error: ' . (json_last_error_msg()));
            flash('error', 'Submission failed: ' . json_last_error_msg());
                redirect('/applications/create');
                return;
            }

            $id = Database::insert(
                "INSERT INTO tenant_applications (property_id, status, data, notes, created_at, updated_at) VALUES (?, 'pending', ?, '', NOW(), NOW())",
                [$propertyId, $json]
            );

            log_activity('application.created', "Tenancy application #{$id} submitted");
            flash('success', __('Your application has been submitted successfully. We will be in touch.'));
            redirect('/applications/thank-you');
        } catch (\Throwable $e) {
            error_log('Application submission failed: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            flash('error', 'Submission failed: ' . $e->getMessage());
            redirect('/applications/create');
        }
    }

    public function thankYou(): void
    {
        $view = new View();
        $view->layout('layouts/guest', ['title' => __('Application Submitted')]);
        $view->render('applications/thank-you');
    }

    public function index(): void
    {
        $showArchived = !empty($_GET['show_archived']);
        $archivedClause = $showArchived ? '' : ' AND a.archived_at IS NULL';

        try {
            $this->ensureTable();
            $applications = Database::fetchAll(
                "SELECT a.*, p.name as property_name 
                 FROM tenant_applications a 
                 LEFT JOIN properties p ON p.id = a.property_id 
                 WHERE 1=1{$archivedClause}
                 ORDER BY a.created_at DESC"
            );
        } catch (\Throwable $e) {
            error_log('ApplicationController@index: ' . $e->getMessage());
            $applications = [];
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => __('Applications')]);
        $view->render('applications/index', [
            'applications' => $applications,
            'showArchived' => $showArchived,
        ]);
    }

    public function show(int $id): void
    {
        try {
            $this->ensureTable();

            $application = Database::fetch(
                "SELECT a.*, p.name as property_name 
                 FROM tenant_applications a 
                 LEFT JOIN properties p ON p.id = a.property_id 
                 WHERE a.id = ?",
                [$id]
            );
        } catch (\Throwable $e) {
            error_log('ApplicationController@show: ' . $e->getMessage());
            http_response_code(500);
            require base_path('www/Views/errors/500.php');
            return;
        }

        if (!$application) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        $data = json_decode($application['data'], true);

        $view = new View();
        $view->layout('layouts/main', ['title' => __('Application') . ' #' . $id]);
        $view->render('applications/show', [
            'application' => $application,
            'data' => $data,
        ]);
    }

    public function updateNotes(int $id): void
    {
        $this->ensureTable();

        if (empty($_POST['notes'])) {
            flash('error', 'Notes cannot be empty.');
            redirect('/applications/' . $id);
        }

        Database::execute(
            "UPDATE tenant_applications SET notes = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['notes'], $id]
        );

        log_activity('application.notes_updated', "Notes updated for application #{$id}");
        flash('success', 'Notes saved.');
        redirect('/applications/' . $id);
    }

    public function updateStatus(int $id): void
    {
        $this->ensureTable();

        $allowed = ['pending', 'reviewed', 'accepted', 'rejected'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, $allowed)) {
            flash('error', 'Invalid status.');
            redirect('/applications/' . $id);
        }

        Database::execute(
            "UPDATE tenant_applications SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $id]
        );

        log_activity('application.status_updated', "Application #{$id} status changed to {$status}");
        flash('success', 'Status updated.');
        redirect('/applications/' . $id);
    }

    public function destroy(int $id): void
    {
        $this->ensureTable();

        Database::execute(
            "UPDATE tenant_applications SET archived_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$id]
        );

        log_activity('application.archived', "Application #{$id} archived");
        flash('success', 'Application archived.');
        redirect('/applications');
    }

    public function restore(int $id): void
    {
        $this->ensureTable();

        Database::execute(
            "UPDATE tenant_applications SET archived_at = NULL, updated_at = NOW() WHERE id = ?",
            [$id]
        );

        log_activity('application.restored', "Application #{$id} restored");
        flash('success', 'Application restored.');
        redirect('/applications?show_archived=1');
    }

    private function buildData(): array
    {
        return [
            'primary_applicant' => [
                'last_name' => $_POST['primary_last_name'] ?? '',
                'first_name' => $_POST['primary_first_name'] ?? '',
                'middle_names' => $_POST['primary_middle_names'] ?? '',
                'birth_date' => $_POST['primary_birth_date'] ?? '',
                'phone' => $_POST['primary_phone'] ?? '',
                'email' => $_POST['primary_email'] ?? '',
                'current_address' => [
                    'street' => $_POST['primary_address_street'] ?? '',
                    'apt_suite' => $_POST['primary_address_apt_suite'] ?? '',
                    'city' => $_POST['primary_address_city'] ?? '',
                    'province' => $_POST['primary_address_province'] ?? '',
                    'postal_code' => $_POST['primary_address_postal_code'] ?? '',
                    'date_moved_in' => $_POST['primary_address_date_moved_in'] ?? '',
                    'reason_leaving' => $_POST['primary_address_reason_leaving'] ?? '',
                ],
                'employment' => [
                    'occupation' => $_POST['primary_employment_occupation'] ?? '',
                    'employer' => $_POST['primary_employment_employer'] ?? '',
                    'street' => $_POST['primary_employment_street'] ?? '',
                    'suite' => $_POST['primary_employment_suite'] ?? '',
                    'city' => $_POST['primary_employment_city'] ?? '',
                    'province' => $_POST['primary_employment_province'] ?? '',
                    'postal_code' => $_POST['primary_employment_postal_code'] ?? '',
                    'start_date' => $_POST['primary_employment_start_date'] ?? '',
                    'supervisor_name' => $_POST['primary_employment_supervisor_name'] ?? '',
                    'phone' => $_POST['primary_employment_phone'] ?? '',
                    'other_income_source' => $_POST['primary_employment_other_income'] ?? '',
                ],
                'background' => [
                    'evicted' => $_POST['primary_background_evicted'] ?? 'no',
                    'evicted_details' => $_POST['primary_background_evicted_details'] ?? '',
                    'convicted' => $_POST['primary_background_convicted'] ?? 'no',
                    'convicted_details' => $_POST['primary_background_convicted_details'] ?? '',
                    'refused_rent' => $_POST['primary_background_refused_rent'] ?? 'no',
                    'refused_rent_details' => $_POST['primary_background_refused_rent_details'] ?? '',
                ],
                'emergency_contact' => [
                    'last_name' => $_POST['primary_emergency_last_name'] ?? '',
                    'first_name' => $_POST['primary_emergency_first_name'] ?? '',
                    'relationship' => $_POST['primary_emergency_relationship'] ?? '',
                    'phone' => $_POST['primary_emergency_phone'] ?? '',
                ],
                'other_info' => $_POST['primary_other_info'] ?? '',
            ],
            'other_tenants' => $this->buildOtherTenants(),
            'other_occupants' => $this->buildOtherOccupants(),
            'references' => $this->buildReferences(),
        ];
    }

    private function buildOtherTenants(): array
    {
        $tenants = [];
        $names = $_POST['other_tenant_last_name'] ?? [];
        foreach ($names as $i => $lastName) {
            if (!is_string($lastName) || trim($lastName) === '') continue;
            $tenants[] = [
                'last_name' => $lastName,
                'first_name' => $_POST['other_tenant_first_name'][$i] ?? '',
                'middle_names' => $_POST['other_tenant_middle_names'][$i] ?? '',
                'birth_date' => $_POST['other_tenant_birth_date'][$i] ?? '',
                'phone' => $_POST['other_tenant_phone'][$i] ?? '',
                'email' => $_POST['other_tenant_email'][$i] ?? '',
                'relationship' => $_POST['other_tenant_relationship'][$i] ?? '',
                'current_address' => [
                    'street' => $_POST['other_tenant_address_street'][$i] ?? '',
                    'apt_suite' => $_POST['other_tenant_address_apt_suite'][$i] ?? '',
                    'city' => $_POST['other_tenant_address_city'][$i] ?? '',
                    'province' => $_POST['other_tenant_address_province'][$i] ?? '',
                    'postal_code' => $_POST['other_tenant_address_postal_code'][$i] ?? '',
                    'date_moved_in' => $_POST['other_tenant_address_date_moved_in'][$i] ?? '',
                    'reason_leaving' => $_POST['other_tenant_address_reason_leaving'][$i] ?? '',
                ],
                'employment' => [
                    'occupation' => $_POST['other_tenant_employment_occupation'][$i] ?? '',
                    'employer' => $_POST['other_tenant_employment_employer'][$i] ?? '',
                    'street' => $_POST['other_tenant_employment_street'][$i] ?? '',
                    'suite' => $_POST['other_tenant_employment_suite'][$i] ?? '',
                    'city' => $_POST['other_tenant_employment_city'][$i] ?? '',
                    'province' => $_POST['other_tenant_employment_province'][$i] ?? '',
                    'postal_code' => $_POST['other_tenant_employment_postal_code'][$i] ?? '',
                    'start_date' => $_POST['other_tenant_employment_start_date'][$i] ?? '',
                    'supervisor_name' => $_POST['other_tenant_employment_supervisor_name'][$i] ?? '',
                    'phone' => $_POST['other_tenant_employment_phone'][$i] ?? '',
                    'other_income_source' => $_POST['other_tenant_employment_other_income'][$i] ?? '',
                ],
                'background' => [
                    'evicted' => $_POST['other_tenant_background_evicted'][$i] ?? 'no',
                    'evicted_details' => $_POST['other_tenant_background_evicted_details'][$i] ?? '',
                    'convicted' => $_POST['other_tenant_background_convicted'][$i] ?? 'no',
                    'convicted_details' => $_POST['other_tenant_background_convicted_details'][$i] ?? '',
                    'refused_rent' => $_POST['other_tenant_background_refused_rent'][$i] ?? 'no',
                    'refused_rent_details' => $_POST['other_tenant_background_refused_rent_details'][$i] ?? '',
                ],
                'emergency_contact' => [
                    'last_name' => $_POST['other_tenant_emergency_last_name'][$i] ?? '',
                    'first_name' => $_POST['other_tenant_emergency_first_name'][$i] ?? '',
                    'relationship' => $_POST['other_tenant_emergency_relationship'][$i] ?? '',
                    'phone' => $_POST['other_tenant_emergency_phone'][$i] ?? '',
                ],
                'other_info' => $_POST['other_tenant_other_info'][$i] ?? '',
            ];
        }
        return $tenants;
    }

    private function buildOtherOccupants(): array
    {
        $occupants = [];
        $names = $_POST['occupant_last_name'] ?? [];
        foreach ($names as $i => $lastName) {
            if (!is_string($lastName) || trim($lastName) === '') continue;
            $occupants[] = [
                'last_name' => $lastName,
                'first_name' => $_POST['occupant_first_name'][$i] ?? '',
                'age' => $_POST['occupant_age'][$i] ?? '',
                'relationship' => $_POST['occupant_relationship'][$i] ?? '',
            ];
        }
        return $occupants;
    }

    private function buildReferences(): array
    {
        $refs = [];
        $lastNames = $_POST['reference_last_name'] ?? [];
        foreach ($lastNames as $i => $lastName) {
            if (!is_string($lastName) || trim($lastName) === '') continue;
            $refs[] = [
                'last_name' => $lastName,
                'first_name' => $_POST['reference_first_name'][$i] ?? '',
                'relationship' => $_POST['reference_relationship'][$i] ?? '',
                'phone' => $_POST['reference_phone'][$i] ?? '',
            ];
        }
        return $refs;
    }

    private function ensureTable(): void
    {
        try {
            Database::query("SELECT 1 FROM tenant_applications LIMIT 1");
        } catch (\Throwable $e) {
            // Table does not exist — try to create it with engine/charset hints
            $created = false;
            $attempts = [
                "CREATE TABLE IF NOT EXISTS tenant_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    property_id INT DEFAULT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    data LONGTEXT NOT NULL,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS tenant_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    property_id INT DEFAULT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    data LONGTEXT NOT NULL,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS tenant_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    data LONGTEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
            ];
            foreach ($attempts as $sql) {
                try {
                    Database::query($sql);
                    $created = true;
                    break;
                } catch (\Throwable $e2) {
                    error_log('ensureTable attempt failed: ' . $e2->getMessage());
                }
            }
            if (!$created) {
                error_log('ensureTable: all CREATE TABLE attempts failed');
                return;
            }
        }
        // Add missing columns (ignore if already exist)
        $alterAttempts = [
            "ALTER TABLE tenant_applications ADD COLUMN property_id INT DEFAULT NULL",
            "ALTER TABLE tenant_applications ADD COLUMN status VARCHAR(20) DEFAULT 'pending'",
            "ALTER TABLE tenant_applications ADD COLUMN notes TEXT",
            "ALTER TABLE tenant_applications ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL",
            "ALTER TABLE tenant_applications ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
            "ALTER TABLE tenant_applications MODIFY COLUMN data LONGTEXT NOT NULL",
        ];
        foreach ($alterAttempts as $sql) {
            try {
                Database::query($sql);
            } catch (\Throwable $e) {
                // Column likely already exists — safe to ignore
            }
        }
        // Add indexes if missing
        foreach (['idx_status (status)', 'idx_created (created_at)'] as $idx) {
            try {
                Database::query("ALTER TABLE tenant_applications ADD INDEX {$idx}");
            } catch (\Throwable $e) {}
        }
    }

    private function getSettings(): array
    {
        $enabled = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'applications_enabled'");
        $notes = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'applications_notes'");
        return [
            'enabled' => ($enabled ?? [])['value'] ?? '0',
            'notes' => ($notes ?? [])['value'] ?? '',
        ];
    }

    public function saveSettings(): void
    {
        $enabled = !empty($_POST['applications_enabled']) ? '1' : '0';
        $notes = $_POST['applications_notes'] ?? '';

        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('applications_enabled', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$enabled, $enabled]
        );
        Database::execute(
            "INSERT INTO settings (`key`, `value`) VALUES ('applications_notes', ?) ON DUPLICATE KEY UPDATE `value` = ?",
            [$notes, $notes]
        );

        log_activity('settings.applications_updated', 'Application settings updated');
        flash('success', 'Application settings saved.');
        redirect('/settings?tab=applications');
    }
}
