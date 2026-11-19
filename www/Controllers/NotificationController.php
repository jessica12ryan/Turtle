<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class NotificationController
{
    private function isXhr(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public function list(): void
    {
        header('Content-Type: application/json');
        $showRead = !empty($_GET['show_read']);
        $readClause = $showRead ? '' : ' AND read_at IS NULL';
        $rows = Database::fetchAll(
            "SELECT * FROM notifications
             WHERE user_id = ? AND (type IS NULL OR type != 'message'){$readClause}
             ORDER BY created_at DESC
             LIMIT 100",
            [Auth::instance()->id()]
        );

        $labels = notificationEventLabels();
        $notifications = [];
        foreach ($rows as $row) {
            $notifications[] = [
                'id' => (int)$row['id'],
                'label' => $labels[$row['type']] ?? ($row['type'] === 'email' ? __('Email') : $row['type']),
                'data' => $row['data'],
                'time' => display_time($row['created_at']),
                'read' => !empty($row['read_at']),
            ];
        }

        echo json_encode($notifications);
    }

    public function unread(): void
    {
        header('Content-Type: application/json');
        $count = Database::fetch(
            "SELECT COUNT(*) as count FROM notifications
             WHERE user_id = ? AND read_at IS NULL AND (type IS NULL OR type != 'message')",
            [Auth::instance()->id()]
        )['count'] ?? 0;
        echo json_encode(['count' => (int)$count]);
    }

    public function index(): void
    {
        $showArchived = !empty($_GET['show_archived']);
        $readClause = $showArchived ? '' : ' AND read_at IS NULL';
        $notifications = Database::fetchAll(
            "SELECT * FROM notifications
             WHERE user_id = ? AND (type IS NULL OR type != 'message'){$readClause}
             ORDER BY created_at DESC",
            [Auth::instance()->id()]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Notifications']);
        $view->render('notifications/index', compact('notifications', 'showArchived'));
    }

    public function read(int $id): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
        Database::execute(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?",
            [$id, Auth::instance()->id()]
        );
        if ($this->isXhr()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            return;
        }
        redirectBack();
    }

    public function readAll(): void
    {
        if (!verify_csrf($_POST['_csrf'] ?? '')) {
            flash('error', __('Invalid form token. Please try again.'));
            redirectBack();
            return;
        }
        Database::execute(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL",
            [Auth::instance()->id()]
        );
        log_activity('notifications.read_all', 'All notifications marked as read');
        if ($this->isXhr()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            return;
        }
        flash('success', 'All notifications marked as read.');
        redirect('/notifications');
    }
}
