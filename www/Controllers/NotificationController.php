<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class NotificationController
{
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
        flash('success', 'All notifications marked as read.');
        redirect('/notifications');
    }
}
