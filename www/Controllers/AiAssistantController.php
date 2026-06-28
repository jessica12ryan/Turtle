<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

class AiAssistantController
{
    public function index(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => 'AI Assistant']);
        $view->render('ai_assistant/index');
    }
}
