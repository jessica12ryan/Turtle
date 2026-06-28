<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

class AiAssistantController
{
    public function index(): void
    {
        $view = new View();
        $view->layout('layouts/main', ['title' => 'AI Assistant']);
        $view->render('ai_assistant/index');
    }

    public function chat(): void
    {
        header('Content-Type: application/json');

        if (!isset($_POST['_csrf']) || !verify_csrf($_POST['_csrf'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid security token.']);
            return;
        }

        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Message is required.']);
            return;
        }

        $apiKey = Database::fetch("SELECT `value` FROM settings WHERE `key` = 'openai_api_key'");
        $apiKey = $apiKey['value'] ?? '';

        if (empty($apiKey)) {
            http_response_code(400);
            echo json_encode(['error' => 'OpenAI API key is not configured. Go to Settings > General to set it up.']);
            return;
        }

        try {
            $user = Auth::instance()->user();
            $context = $this->buildContext($user);
            $systemPrompt = $this->buildSystemPrompt($user, $context);

            if (!isset($_SESSION['ai_conversation'])) {
                $_SESSION['ai_conversation'] = [];
            }

            $conversation = $_SESSION['ai_conversation'];
            $conversation[] = ['role' => 'user', 'content' => $message];

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach (array_slice($conversation, -20) as $msg) {
                $messages[] = $msg;
            }

            $response = $this->callOpenAI($apiKey, $messages);
            $conversation[] = ['role' => 'assistant', 'content' => $response];
            $_SESSION['ai_conversation'] = $conversation;

            echo json_encode(['response' => $response]);
        } catch (\Throwable $e) {
            error_log('AI Assistant error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function buildSystemPrompt(array $user, array $context): string
    {
        $roleLabel = ucwords(str_replace('_', ' ', $user['role']));

        $prompt = "You are an AI assistant for a property management application called Turtle. ";
        $prompt .= "The current user is {$user['name']} ({$roleLabel}). ";
        $prompt .= "Answer questions based ONLY on the data provided below. If the information is not available, say so.\n\n";
        $prompt .= "=== CURRENT DATA ===\n";

        if (!empty($context['stats'])) {
            $prompt .= "Stats:\n";
            foreach ($context['stats'] as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }

        if (!empty($context['properties'])) {
            $prompt .= "\nProperties:\n";
            foreach ($context['properties'] as $p) {
                $tenants = $p['tenants_count'] ?? 0;
                $tickets = $p['tickets_count'] ?? 0;
                $prompt .= "- {$p['name']} (landlord: {$p['landlord_name']}, tenants: {$tenants}, open tickets: {$tickets})\n";
            }
        }

        if (!empty($context['tenants'])) {
            $prompt .= "\nActive Tenants:\n";
            foreach ($context['tenants'] as $t) {
                $prompt .= "- {$t['name']} ({$t['email']})";
                if (!empty($t['property_name'])) {
                    $prompt .= " at {$t['property_name']}";
                }
                $prompt .= "\n";
            }
        }

        if (!empty($context['open_tickets'])) {
            $prompt .= "\nOpen Tickets:\n";
            foreach ($context['open_tickets'] as $t) {
                $prompt .= "- [{$t['status']}] {$t['subject']} ({$t['property_name']} - {$t['tenant_name']})\n";
            }
        }

        if (!empty($context['recent_leases'])) {
            $prompt .= "\nRecent Leases:\n";
            foreach ($context['recent_leases'] as $l) {
                $prompt .= "- {$l['property_name']}: {$l['lease_start']}";
                if (!empty($l['lease_end'])) {
                    $prompt .= " to {$l['lease_end']}";
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "\nKeep responses concise and helpful. Format with markdown where appropriate.";
        return $prompt;
    }

    private function buildContext(array $user): array
    {
        $auth = Auth::instance();
        $role = $user['role'];
        $context = [];

        $isAdmin = $role === 'admin';

        if ($isAdmin) {
            $propertyIds = "SELECT id FROM properties WHERE archived_at IS NULL";
        } else {
            $companyIds = Database::fetchAll(
                "SELECT company_id FROM company_user WHERE user_id = ?",
                [$auth->id()]
            );
            $companyIds = array_column($companyIds, 'company_id');
            $companyIdList = implode(',', array_map('intval', $companyIds)) ?: '0';
            $pmClause = $role === 'property_manager' ? " AND property_manager_id = {$auth->id()}" : '';
            $propertyIds = "SELECT id FROM properties WHERE company_id IN ({$companyIdList}) AND archived_at IS NULL{$pmClause}";
        }

        $props = Database::fetchAll("SELECT id FROM properties WHERE id IN ({$propertyIds}) AND archived_at IS NULL");
        $propertyIdList = implode(',', array_column($props, 'id')) ?: '0';

        $context['stats'] = [];
        $context['stats']['Total Properties'] = count($props);
        $context['stats']['Active Tenants'] = Database::fetch(
            "SELECT COUNT(*) as count FROM property_tenant WHERE property_id IN ({$propertyIdList}) AND moved_out_at IS NULL"
        )['count'] ?? 0;
        $context['stats']['Open Tickets'] = Database::fetch(
            "SELECT COUNT(*) as count FROM tickets WHERE property_id IN ({$propertyIdList}) AND status IN ('open','in_progress','awaiting_parts','awaiting_contractor') AND archived_at IS NULL"
        )['count'] ?? 0;
        $context['stats']['Active Leases'] = Database::fetch(
            "SELECT COUNT(*) as count FROM leases WHERE property_id IN ({$propertyIdList}) AND archived_at IS NULL"
        )['count'] ?? 0;

        $context['properties'] = Database::fetchAll(
            "SELECT p.id, p.name, u.name as landlord_name,
             (SELECT COUNT(*) FROM property_tenant WHERE property_id = p.id AND moved_out_at IS NULL) as tenants_count,
             (SELECT COUNT(*) FROM tickets WHERE property_id = p.id AND archived_at IS NULL) as tickets_count
             FROM properties p JOIN users u ON u.id = p.landlord_id
             WHERE p.id IN ({$propertyIdList}) AND p.archived_at IS NULL
             ORDER BY p.name"
        );

        $context['tenants'] = Database::fetchAll(
            "SELECT u.name, u.email, p.name as property_name FROM users u
             JOIN property_tenant pt ON pt.tenant_id = u.id
             JOIN properties p ON p.id = pt.property_id
             WHERE pt.property_id IN ({$propertyIdList}) AND pt.moved_out_at IS NULL AND u.archived_at IS NULL
             ORDER BY u.name"
        );

        $context['open_tickets'] = Database::fetchAll(
            "SELECT t.subject, t.status, p.name as property_name, u.name as tenant_name FROM tickets t
             JOIN properties p ON p.id = t.property_id
             JOIN users u ON u.id = t.tenant_id
             WHERE t.property_id IN ({$propertyIdList}) AND t.archived_at IS NULL
             AND t.status IN ('open','in_progress','awaiting_parts','awaiting_contractor')
             ORDER BY t.created_at DESC LIMIT 20"
        );

        $context['recent_leases'] = Database::fetchAll(
            "SELECT p.name as property_name, l.lease_start, l.lease_end FROM leases l
             JOIN properties p ON p.id = l.property_id
             WHERE l.property_id IN ({$propertyIdList}) AND l.archived_at IS NULL
             ORDER BY l.created_at DESC LIMIT 10"
        );

        return $context;
    }

    private function callOpenAI(string $apiKey, array $messages): string
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 1500,
            'temperature' => 0.7,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('OpenAI API request failed: ' . $error);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errMsg = $data['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException('OpenAI API error (' . $httpCode . '): ' . $errMsg);
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
