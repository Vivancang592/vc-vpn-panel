<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\FanpageService;

class FanpageWebhookController extends BaseController
{
    public function verify(): void
    {
        $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
        $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

        $service = new FanpageService();
        $result = $service->verifyToken($mode, $token, $challenge);

        if ($result === null) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid verify token';
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo $result;
    }

    public function webhook(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);
        $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');

        $service = new FanpageService();
        if (!$service->validateSignature((string) $raw, $signature)) {
            $this->json(['success' => false, 'message' => 'Invalid signature'], 401);
            return;
        }

        if (!is_array($payload)) {
            $this->json(['success' => false, 'message' => 'Payload khong hop le'], 400);
            return;
        }

        $service->handleWebhook($payload);

        $this->json(['success' => true, 'message' => 'EVENT_RECEIVED']);
    }
}
