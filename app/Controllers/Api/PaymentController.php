<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\OrderService;
use App\Services\PaymentService;

class PaymentController extends BaseController
{
    public function webhook(): void
    {
        // 1. Nhận dữ liệu đa dạng (JSON, $_POST, hoặc raw form-urlencoded)
        $rawInput = file_get_contents('php://input');
        $payload  = json_decode($rawInput, true);

        if (empty($payload) && !empty($_POST)) {
            $payload = $_POST;
        }

        if (empty($payload) && !empty($rawInput)) {
            parse_str($rawInput, $payload);
        }

        // 2. Ghi Log vào thư mục bảo mật storage/logs/
        $logFile = __DIR__ . '/../../../storage/logs/macrodroid_debug.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logData  = date('[Y-m-d H:i:s]') . " ---- INCOMING WEBHOOK ----\n";
        $logData .= "RAW INPUT: " . $rawInput . "\n";
        $logData .= "PARSED PAYLOAD: " . print_r($payload, true) . "\n";
        $logData .= "-------------------------------------------\n\n";
        @file_put_contents($logFile, $logData, FILE_APPEND);

        if (empty($payload)) {
            $this->json(['status' => false, 'message' => 'Dữ liệu Webhook không hợp lệ.'], 400);
            return;
        }

        // 3. Kiểm tra Secret Token
        $config = require __DIR__ . '/../../../config/app.php';
        $expectedSecret = $config['macrodroid_secret'] ?? '';
        $providedSecret = $payload['secret'] ?? $_SERVER['HTTP_X_MACRODROID_SECRET'] ?? '';

        if (!empty($expectedSecret) && !hash_equals($expectedSecret, $providedSecret)) {
            $this->json(['status' => false, 'message' => 'Mã xác thực Webhook không hợp lệ.'], 403);
            return;
        }

        $content = trim($payload['content'] ?? $payload['description'] ?? '');
        $amount  = (float)($payload['amount'] ?? 0);
        $transId = $payload['transaction_id'] ?? '';

        $orderService   = new OrderService();
        $paymentService = new PaymentService();

        // 4. VietQR: Kiểm tra mã đơn hàng LS...
        if (!empty($content) && preg_match('/LS\d+/i', $content, $matches)) {
            $orderCode = strtoupper($matches[0]);
            
            if ($amount <= 0 && preg_match('/(?:\+|KH:\s*|TIEN:\s*|^)(\d+(?:\.\d+)?)/i', $content, $amtMatches)) {
                $amount = (float)$amtMatches[1];
            }

            $result = $orderService->processPaymentByOrderCode($orderCode, $amount, $transId ?: $orderCode);
            $this->json(['status' => $result['status'], 'message' => $result['message']], $result['status'] ? 200 : 400);
            return;
        }

        // 5. VietQR: Kiểm tra mã nạp tiền DEP...
        if (!empty($content) && preg_match('/DEP\d+/i', $content, $matches)) {
            $transCode = strtoupper($matches[0]);
            $result = $paymentService->completePaymentByCode($transCode, $amount);

            if ($result) {
                $this->json(['status' => true, 'message' => 'Nạp tiền vào tài khoản thành công.']);
            } else {
                $this->json(['status' => false, 'message' => 'Xử lý mã nạp tiền thất bại hoặc đã được xử lý.'], 400);
            }
            return;
        }

        // 6. WeChat Pay / VietQR: Tự động bóc tách số tiền từ nội dung thông báo
        if ($amount <= 0 && !empty($content)) {
            $cleanContent = str_replace(',', '.', $content);
            
            // Ưu tiên 1: Tìm số đứng ngay trước chữ 元 (Ví dụ: 0.10元, 0.50元)
            if (preg_match('/(\d+(?:\.\d+)?)\s*元/u', $cleanContent, $amtMatches)) {
                $amount = (float)$amtMatches[1];
            } 
            // Ưu tiên 2: Tìm số có dấu chấm thập phân (VD: 0.10) để né số chỉ mục [2] ở đầu
            elseif (preg_match('/(\d+\.\d+)/', $cleanContent, $amtMatches)) {
                $amount = (float)$amtMatches[1];
            }
            // Ưu tiên 3: Lấy số bất kỳ
            elseif (preg_match('/(\d+(?:\.\d+)?)/', $cleanContent, $amtMatches)) {
                $amount = (float)$amtMatches[1];
            }
        }

        if ($amount <= 0) {
            $this->json([
                'status' => false, 
                'message' => 'Không bóc tách được số tiền.'
            ], 400);
            return;
        }

        // 7. Khớp đơn tự động WeChat Pay theo số tiền
        $result = $orderService->processPaymentByAmount($amount, $transId);
        $this->json(['status' => $result['status'], 'message' => $result['message']], $result['status'] ? 200 : 404);
    }
}