<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class NotificationController
{
    public function index(): void
    {
        $notifications = Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC",
            [Auth::instance()->id()]
        );

        $view = new View();
        $view->layout('layouts/main', ['title' => 'Notifications']);
        $view->render('notifications/index', compact('notifications'));
    }

    public function read(int $id): void
    {
        Database::execute(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?",
            [$id, Auth::instance()->id()]
        );
        redirectBack();
    }

    public function readAll(): void
    {
        Database::execute(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL",
            [Auth::instance()->id()]
        );
        log_activity('notifications.read_all', 'All notifications marked as read');
        flash('success', 'All notifications marked as read.');
        redirect('/notifications');
    }
}
