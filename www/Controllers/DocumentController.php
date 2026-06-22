<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

class DocumentController
{
    public function download(int $id): void
    {
        $document = Database::fetch("SELECT * FROM documents WHERE id = ? AND archived_at IS NULL", [$id]);
        if (!$document) { http_response_code(404); require base_path('www/Views/errors/404.php'); return; }

        $filePath = $document['file_path'];
        if (str_starts_with($filePath, '/')) {
            $fullPath = $filePath;
        } else {
            $fullPath = base_path($filePath);
        }
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }

        header('Content-Type: ' . ($document['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    public function destroy(int $id): void
    {
        $document = Database::fetch("SELECT * FROM documents WHERE id = ?", [$id]);
        if ($document) {
            $filePath = $document['file_path'];
            $fullPath = str_starts_with($filePath, '/') ? $filePath : base_path($filePath);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            Database::execute("UPDATE documents SET archived_at = NOW() WHERE id = ?", [$id]);
        }

        flash('success', 'Document deleted successfully.');
        redirectBack();
    }
}
