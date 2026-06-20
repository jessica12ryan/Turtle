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

        $filePath = base_path($document['file_path']);
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }

        header('Content-Type: ' . ($document['mime_type'] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function destroy(int $id): void
    {
        $document = Database::fetch("SELECT * FROM documents WHERE id = ?", [$id]);
        if ($document) {
            $filePath = base_path($document['file_path']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            Database::execute("UPDATE documents SET archived_at = NOW() WHERE id = ?", [$id]);
        }

        flash('success', 'Document deleted successfully.');
        redirectBack();
    }
}
