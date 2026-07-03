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
            "SELECT id, name, address, city, province FROM properties 
             WHERE archived_at IS NULL 
             AND id NOT IN (SELECT property_id FROM property_tenant WHERE moved_out_at IS NULL)
             ORDER BY name"
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

            if (!verify_csrf($_POST['_csrf'] ?? '')) {
                flash('error', 'Invalid form token. Please try again.');
                redirect('/applications/create');
            }

            $this->handlePhotoUploads();

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
                "INSERT INTO tenant_applications (property_id, status, data, notes, created_at, updated_at) VALUES (?, 'new', ?, '', NOW(), NOW())",
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

        $allowed = ['new', 'in_progress', 'accepted', 'rejected'];
        $status = $_POST['status'] ?? '';
        if (!in_array($status, $allowed)) {
            flash('error', 'Invalid status.');
            redirect('/applications/' . $id);
        }

        if ($status === 'accepted') {
            redirect('/applications/' . $id . '/convert');
        }

        Database::execute(
            "UPDATE tenant_applications SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $id]
        );

        log_activity('application.status_updated', "Application #{$id} status changed to {$status}");
        flash('success', 'Status updated.');
        redirect('/applications/' . $id);
    }

    public function convert(int $id): void
    {
        $this->ensureTable();

        // Clear stale old form data when navigating to a different application
        if (isset($_SESSION['_old']) && (!isset($_SESSION['_old_app_id']) || $_SESSION['_old_app_id'] != $id)) {
            unset($_SESSION['_old'], $_SESSION['_old_app_id']);
        }

        $application = Database::fetch(
            "SELECT a.*, p.name as property_name
             FROM tenant_applications a
             LEFT JOIN properties p ON p.id = a.property_id
             WHERE a.id = ?",
            [$id]
        );
        if (!$application) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $data = json_decode($application['data'], true);

        $user = Auth::instance()->user();
        if ($user['role'] === 'admin') {
            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p
                 JOIN users u ON u.id = p.landlord_id
                 WHERE p.archived_at IS NULL
                 AND p.id NOT IN (SELECT property_id FROM property_tenant WHERE moved_out_at IS NULL)
                 ORDER BY p.name"
            );
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$user['id']]
            );
            $companyIdList = implode(',', array_column($companyIds, 'company_id')) ?: '0';
            $pmClause = $user['role'] === 'property_manager' ? ' AND p.property_manager_id = ?' : '';
            $params = $pmClause ? [$user['id']] : [];

            $properties = Database::fetchAll(
                "SELECT p.*, u.name as landlord_name FROM properties p
                 JOIN users u ON u.id = p.landlord_id
                 WHERE p.company_id IN ({$companyIdList}) AND p.archived_at IS NULL{$pmClause}
                 AND p.id NOT IN (SELECT property_id FROM property_tenant WHERE moved_out_at IS NULL)
                 ORDER BY p.name",
                $params
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => __('Convert Application') . ' #' . $id]);
        $view->render('applications/convert', compact('application', 'data', 'properties'));
    }

    public function processConvert(int $id): void
    {
        try {
            $this->ensureTable();

            if (!verify_csrf($_POST['_csrf'] ?? '')) {
                flash('error', 'Invalid form token. Please try again.');
                redirect('/applications/' . $id . '/convert');
            }

            $application = Database::fetch("SELECT * FROM tenant_applications WHERE id = ?", [$id]);
            if (!$application) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

            $appData = json_decode($application['data'], true);
            $mainApplicant = $appData['primary_applicant'] ?? [];

            $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
            if (strlen($phone) === 10) {
                $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
            }

            $password = bin2hex(random_bytes(6));
            $timezone = $_POST['timezone'] ?: null;
            $language = $_POST['language'] ?: null;

            Database::beginTransaction();

            $archived = Database::fetch("SELECT id FROM users WHERE email = ? AND archived_at IS NOT NULL", [$_POST['email']]);
            if ($archived) {
                flash('error', 'Email exists in archived tenant.');
                $_SESSION['_old'] = $_POST;
                $_SESSION['_old_app_id'] = $id;
                Database::rollback();
                redirect('/applications/' . $id . '/convert');
            }

            $validator = new \App\Core\Validator();
            $rules = [
                'name' => 'required|max:255',
                'email' => 'required|email|unique:users,email',
                'property_id' => 'required|exists:properties,id',
                'phone' => 'required|max:20',
                'lease_start' => 'required',
                'lease_type' => 'required',
            ];
            if (!$validator->validate($_POST, $rules)) {
                $_SESSION['_errors'] = $validator->errors();
                $_SESSION['_old'] = $_POST;
                $_SESSION['_old_app_id'] = $id;
                Database::rollback();
                redirect('/applications/' . $id . '/convert');
            }

            $tenantId = Database::insert(
                "INSERT INTO users (name, email, phone, password, role, theme, timezone, language, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 'tenant', 'system', ?, ?, 1, NOW(), NOW())",
                [$_POST['name'], $_POST['email'], $phone, password_hash($password, PASSWORD_DEFAULT), $timezone, $language]
            );

            Database::insert(
                "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, lease_start, lease_end, move_out_date, lease_type, emergency_contact_name, emergency_contact_phone, created_at, updated_at) VALUES (?, ?, 1, NOW(), ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $_POST['property_id'], $tenantId,
                    $_POST['lease_start'] ?: null,
                    $_POST['lease_end'] ?: null,
                    $_POST['move_out_date'] ?: null,
                    $_POST['lease_type'] ?: null,
                    $_POST['emergency_contact_name'] ?: null,
                    $_POST['emergency_contact_phone'] ?: null,
                ]
            );

            // Upload each photo ID as a separate lease document
            $uploadDir = base_path('storage/uploads/leases');
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $idDocuments = [];
            if (!empty($mainApplicant['photo_id'])) {
                $name = strtoupper(trim(($mainApplicant['first_name'] ?? '') . ' ' . ($mainApplicant['middle_names'] ?? '') . ' ' . ($mainApplicant['last_name'] ?? '')));
                $idDocuments[] = ['path' => $mainApplicant['photo_id'], 'label' => 'ID - ' . $name];
            }
            foreach ($appData['other_tenants'] ?? [] as $i => $ot) {
                if (!empty($ot['photo_id'])) {
                    $name = strtoupper(trim(($ot['first_name'] ?? '') . ' ' . ($ot['middle_names'] ?? '') . ' ' . ($ot['last_name'] ?? '')));
                    $idDocuments[] = ['path' => $ot['photo_id'], 'label' => 'ID - ' . $name];
                }
            }

            foreach ($idDocuments as $item) {
                $srcFull = base_path($item['path']);
                if (!file_exists($srcFull)) continue;

                $leaseId = Database::insert(
                    "INSERT INTO leases (property_id, tenant_id, title, description, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, 'Converted from application', ?, NOW(), NOW())",
                    [$_POST['property_id'], $tenantId, $item['label'], Auth::instance()->id()]
                );

                $leaseDir = $uploadDir . '/' . $leaseId;
                if (!is_dir($leaseDir)) mkdir($leaseDir, 0777, true);

                $ext = pathinfo($srcFull, PATHINFO_EXTENSION);
                $storedName = uniqid() . '.' . $ext;
                $destPath = $leaseDir . '/' . $storedName;

                if (copy($srcFull, $destPath)) {
                    $pathPrefix = str_starts_with($leaseDir, base_path())
                        ? 'storage/uploads/leases'
                        : $leaseDir;
                    $filePath = $pathPrefix . '/' . $leaseId . '/' . $storedName;
                    Database::insert(
                        "INSERT INTO documents (documentable_type, documentable_id, file_path, original_name, size, mime_type, uploaded_by, created_at, updated_at) VALUES ('lease', ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$leaseId, $filePath, $item['label'], filesize($destPath), mime_content_type($destPath) ?: 'application/octet-stream', Auth::instance()->id()]
                    );
                }
            }

            // Create secondary tenants
            $secondaryNames = $_POST['secondary_name'] ?? [];
            $secondaryEmails = $_POST['secondary_email'] ?? [];
            $secondaryPhones = $_POST['secondary_phone'] ?? [];

            foreach ($secondaryNames as $i => $secName) {
                $secName = trim($secName);
                if ($secName === '') continue;

                $secEmail = trim($secondaryEmails[$i] ?? '');
                $secPhone = preg_replace('/[^0-9]/', '', $secondaryPhones[$i] ?? '');
                if (strlen($secPhone) === 10) {
                    $secPhone = '(' . substr($secPhone, 0, 3) . ') ' . substr($secPhone, 3, 3) . '-' . substr($secPhone, 6, 4);
                }

                if ($secEmail !== '') {
                    $existing = Database::fetch("SELECT id FROM users WHERE email = ? AND archived_at IS NULL", [$secEmail]);
                    if ($existing) continue;
                }

                $secPassword = $secEmail !== '' ? bin2hex(random_bytes(6)) : null;
                $secTenantId = null;

                if ($secEmail !== '') {
                    $secTenantId = Database::insert(
                        "INSERT INTO users (name, email, phone, password, role, theme, must_change_password, created_at, updated_at) VALUES (?, ?, ?, ?, 'tenant', 'system', 1, NOW(), NOW())",
                        [$secName, $secEmail, $secPhone, password_hash($secPassword, PASSWORD_DEFAULT)]
                    );
                } else {
                    $secTenantId = Database::insert(
                        "INSERT INTO users (name, email, phone, password, role, theme, must_change_password, created_at, updated_at) VALUES (?, '', ?, ?, 'tenant', 'system', 0, NOW(), NOW())",
                        [$secName, $secPhone, password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT)]
                    );
                }

                Database::insert(
                    "INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW(), NOW())",
                    [$_POST['property_id'], $secTenantId]
                );

                if ($secEmail !== '' && !empty($_POST['send_welcome_email'])) {
                    $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
                    \App\Core\Mailer::sendTemplate(
                        $secEmail,
                        'Welcome to Turtle - Your Account Has Been Created',
                        'Hello ' . h($secName) . ',',
                        'Your account has been created on the Turtle Portal as part of a new tenancy.<br><br><strong>Your temporary password is: ' . $secPassword . '</strong><br><br>Please log in and change your password immediately.',
                        $loginUrl,
                        'Log In'
                    );
                }
            }

            Database::execute(
                "UPDATE tenant_applications SET status = 'accepted', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            Database::commit();

            if (!empty($_POST['send_welcome_email'])) {
                $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/login';
                \App\Core\Mailer::sendTemplate(
                    $_POST['email'],
                    'Welcome to Turtle - Your Account Has Been Created',
                    'Hello ' . h($_POST['name']) . ',',
                    'Your tenancy application has been accepted! Your account has been created on the Turtle Portal.<br><br><strong>Your temporary password is: ' . $password . '</strong><br><br>Please log in and change your password immediately.',
                    $loginUrl,
                    'Log In'
                );
            }

            log_activity('tenant.created', "Tenant '{$_POST['name']}' added from application #{$id}");
            log_activity('application.status_updated', "Application #{$id} accepted and converted to tenant");

            flash('success', 'Application accepted and tenant created successfully.');
            redirect('/tenants');
        } catch (\Throwable $e) {
            Database::rollback();
            error_log('Application conversion failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
            flash('error', 'Failed to create tenant: ' . $e->getMessage());
            $_SESSION['_old'] = $_POST;
            $_SESSION['_old_app_id'] = $id;
            redirect('/applications/' . $id . '/convert');
        }
    }

    public function archive(int $id): void
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

    public function delete(int $id): void
    {
        $this->ensureTable();

        Database::execute(
            "DELETE FROM tenant_applications WHERE id = ?",
            [$id]
        );

        log_activity('application.deleted', "Application #{$id} permanently deleted");
        flash('success', 'Application permanently deleted.');
        redirect('/applications');
    }

    private function buildData(): array
    {
        return [
            'expected_move_in_date' => $_POST['expected_move_in_date'] ?? '',
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
                'photo_id' => $_POST['primary_photo_id'] ?? '',
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
                'photo_id' => $_POST['other_tenant_photo_id'][$i] ?? '',
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
                    status VARCHAR(20) DEFAULT 'new',
                    data LONGTEXT NOT NULL,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS tenant_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    property_id INT DEFAULT NULL,
                    status VARCHAR(20) DEFAULT 'new',
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
            "ALTER TABLE tenant_applications ADD COLUMN status VARCHAR(20) DEFAULT 'new'",
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

    private function handlePhotoUploads(): void
    {
        $uploadDir = base_path('storage/uploads/application_photos');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!empty($_FILES['primary_photo_id']) && $_FILES['primary_photo_id']['error'] === UPLOAD_ERR_OK) {
            $path = $this->moveUploadedFile($_FILES['primary_photo_id'], $uploadDir);
            if ($path) {
                $_POST['primary_photo_id'] = $path;
            }
        }

        if (!empty($_FILES['other_tenant_photo_id']) && is_array($_FILES['other_tenant_photo_id']['name'])) {
            foreach ($_FILES['other_tenant_photo_id']['name'] as $i => $name) {
                if (!empty($name) && ($_FILES['other_tenant_photo_id']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['other_tenant_photo_id']['name'][$i],
                        'type' => $_FILES['other_tenant_photo_id']['type'][$i],
                        'tmp_name' => $_FILES['other_tenant_photo_id']['tmp_name'][$i],
                        'error' => $_FILES['other_tenant_photo_id']['error'][$i],
                        'size' => $_FILES['other_tenant_photo_id']['size'][$i],
                    ];
                    $path = $this->moveUploadedFile($file, $uploadDir);
                    if ($path) {
                        $_POST['other_tenant_photo_id'][$i] = $path;
                    }
                }
            }
        }
    }

    private function moveUploadedFile(array $file, string $uploadDir): ?string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (!in_array($ext, $allowed)) {
            return null;
        }

        $filename = uniqid('app_photo_') . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            return 'storage/uploads/application_photos/' . $filename;
        }

        return null;
    }

    public function servePhoto(int $id, string $key): void
    {
        try {
            $this->ensureTable();
            $application = Database::fetch(
                "SELECT data FROM tenant_applications WHERE id = ?",
                [$id]
            );
        } catch (\Throwable $e) {
            http_response_code(500);
            exit;
        }

        if (!$application) {
            http_response_code(404);
            exit;
        }

        $data = json_decode($application['data'], true);
        $filePath = null;

        if ($key === 'primary' && !empty($data['primary_applicant']['photo_id'])) {
            $filePath = $data['primary_applicant']['photo_id'];
        } elseif (str_starts_with($key, 'tenant_')) {
            $index = (int) substr($key, 7);
            if (!empty($data['other_tenants'][$index]['photo_id'])) {
                $filePath = $data['other_tenants'][$index]['photo_id'];
            }
        }

        if (!$filePath) {
            http_response_code(404);
            exit;
        }

        $fullPath = base_path($filePath);
        if (!file_exists($fullPath)) {
            http_response_code(404);
            exit;
        }

        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
    }
}
