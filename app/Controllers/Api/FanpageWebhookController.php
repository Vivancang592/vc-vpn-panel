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

        // Lấy signature từ nhiều biến môi trường header để đảm bảo tương thích mọi webserver
        $signature = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] 
            ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] 
            ?? $_SERVER['X_HUB_SIGNATURE_256'] 
            ?? $_SERVER['X_HUB_SIGNATURE'] 
            ?? '');

        if ($signature === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'X-Hub-Signature-256') === 0 || strcasecmp($k, 'X-Hub-Signature') === 0) {
                    $signature = (string) $v;
                    break;
                }
            }
        }

        $service = new FanpageService();
        if (!$service->validateSignature((string) $raw, $signature)) {
            $service->logSignatureFailure((string) $raw, $signature);
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
