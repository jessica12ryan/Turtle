<?php

namespace App\Controllers;

use App\Core\Access;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class DocumentController
{
    public function show(int $id): void
    {
        $document = Database::fetch(
            "SELECT d.*, u.name as uploader_name
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.id = ?",
            [$id]
        );
        if (!$document) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::document($id));

        $lease = null;
        if ($document['documentable_type'] === 'lease') {
            $lease = Database::fetch(
                "SELECT l.id, l.title, p.name as property_name
                 FROM leases l
                 LEFT JOIN properties p ON p.id = l.property_id
                 WHERE l.id = ?",
                [$document['documentable_id']]
            );
        }

        $view = new View();
        $view->layout('layouts/main', ['title' => $document['original_name']]);
        $view->render('documents/show', compact('document', 'lease'));
    }

    public function view(int $id): void
    {
        $document = Database::fetch("SELECT * FROM documents WHERE id = ?", [$id]);
        if (!$document) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::document($id));

        $filePath = $document['file_path'];
        if (str_starts_with($filePath, '/')) {
            $fullPath = $filePath;
        } else {
            $fullPath = base_path($filePath);
        }
        if (!file_exists($fullPath)) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        // Never trust the stored MIME for inline delivery: a forged row could
        // otherwise serve HTML/SVG same-origin. Verify the actual file type.
        $inlineMimes = [
            'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'image/heic', 'image/heif',
        ];
        $actualMime = strtolower((string)((new \finfo(FILEINFO_MIME_TYPE))->file($fullPath)));
        $isInline = in_array($actualMime, $inlineMimes, true);
        $filename = sanitize_filename($document['original_name']);

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . ($isInline ? $actualMime : 'application/octet-stream'));
        header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    public function download(int $id): void
    {
        $document = Database::fetch("SELECT * FROM documents WHERE id = ?", [$id]);
        if (!$document) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::document($id));

        $filePath = $document['file_path'];
        if (str_starts_with($filePath, '/')) {
            $fullPath = $filePath;
        } else {
            $fullPath = base_path($filePath);
        }
        if (!file_exists($fullPath)) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }

        header('Content-Type: ' . ($document['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . sanitize_filename($document['original_name']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    public function destroy(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }

        $document = Database::fetch("SELECT * FROM documents WHERE id = ?", [$id]);
        if (!$document) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::document($id));

        $this->deleteDocumentFile($document);
        Database::execute("UPDATE documents SET archived_at = NOW() WHERE id = ?", [$id]);

        log_activity('document.deleted', "Document #{$id} '" . $document['original_name'] . "' archived");
        flash('success', __('Document archived successfully.'));
        redirect('/documents/' . $id);
    }

    public function restore(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }

        $document = Database::fetch("SELECT id FROM documents WHERE id = ?", [$id]);
        if (!$document) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::document($id));

        Database::execute("UPDATE documents SET archived_at = NULL WHERE id = ?", [$id]);

        log_activity('document.restored', "Document #{$id} restored");
        flash('success', __('Document restored successfully.'));
        redirect('/documents/' . $id);
    }

    public function hardDelete(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
        }

        $document = Database::fetch("SELECT * FROM documents WHERE id = ?", [$id]);
        if (!$document) {
            http_response_code(404);
            require base_path('www/Views/errors/404.php');
            return;
        }
        Access::require(Access::document($id));

        $this->deleteDocumentFile($document);
        Database::execute("DELETE FROM documents WHERE id = ?", [$id]);

        log_activity('document.deleted', "Document #{$id} '" . $document['original_name'] . "' permanently deleted");
        flash('success', __('Document permanently deleted.'));
        redirect('/leases');
    }

    private function deleteDocumentFile(array $document): void
    {
        $filePath = $document['file_path'];
        $fullPath = str_starts_with($filePath, '/') ? $filePath : base_path($filePath);
        $allowedBase = realpath(base_path('storage/uploads'));
        $realFull = realpath($fullPath);
        if ($realFull !== false && $allowedBase !== false && str_starts_with($realFull, $allowedBase) && file_exists($realFull)) {
            unlink($realFull);
        } elseif (file_exists($fullPath)) {
            $normalized = str_replace('\\', '/', $fullPath);
            if (str_contains($normalized, 'storage/uploads/')) {
                @unlink($fullPath);
            }
        }
    }
}
