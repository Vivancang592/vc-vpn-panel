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

        $orderService   = new OrderService();
        $paymentService = new PaymentService();
        $paymentModel   = new \App\Models\Payment();
        $settings       = (new \App\Models\Setting())->getAllAsKeyValue();
        $config         = require __DIR__ . '/../../../config/app.php';

        // 3. Kiểm tra API Key / Secret Token (Hỗ trợ SePay & MacroDroid)
        $providedKey = (string)($payload['api_key'] ?? $payload['apiKey'] ?? $payload['apikey'] ?? $payload['secret'] ?? $payload['token'] ?? '');
        
        if ($providedKey === '') {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/^(?:Apikey|Bearer)\s+(.*)$/i', $authHeader, $matches)) {
                $providedKey = trim($matches[1]);
            } elseif (!empty($authHeader)) {
                $providedKey = trim($authHeader);
            }
        }

        if ($providedKey === '') {
            $providedKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_APIKEY'] ?? $_SERVER['HTTP_X_MACRODROID_SECRET'] ?? $_GET['api_key'] ?? $_GET['apiKey'] ?? $_GET['key'] ?? $_GET['secret'] ?? '');
        }

        // Danh sách các API Key / Secret hợp lệ được cấu hình
        $validKeys = array_filter([
            trim((string)($config['macrodroid_secret'] ?? '')),
            trim((string)($config['sepay_api_key'] ?? '')),
            trim((string)getenv('SEPAY_API_KEY')),
            trim((string)getenv('MACRODROID_SECRET')),
            trim((string)($settings['sepay_api_key'] ?? '')),
            trim((string)($settings['payment_api_key'] ?? '')),
            trim((string)($settings['webhook_api_key'] ?? '')),
            trim((string)($settings['macrodroid_secret'] ?? '')),
        ]);

        if (!empty($validKeys)) {
            $isAuthenticated = false;
            foreach ($validKeys as $validKey) {
                if (hash_equals($validKey, $providedKey)) {
                    $isAuthenticated = true;
                    break;
                }
            }

            if (!$isAuthenticated) {
                $this->json(['status' => false, 'message' => 'API Key hoặc mã xác thực Webhook không hợp lệ.'], 403);
                return;
            }
        }

        // Bỏ qua nếu là giao dịch tiền ra từ SePay (transferType != in)
        if (isset($payload['transferType']) && strtolower((string)$payload['transferType']) !== 'in') {
            $this->json(['status' => false, 'message' => 'Bỏ qua giao dịch không phải tiền vào.'], 200);
            return;
        }

        $content = trim((string)($payload['content'] ?? $payload['description'] ?? ''));
        $searchContent = trim($content . ' ' . (string)($payload['description'] ?? '') . ' ' . (string)($payload['code'] ?? ''));
        $amount  = (float)($payload['transferAmount'] ?? $payload['amount'] ?? 0);
        $transId = (string)($payload['referenceCode'] ?? $payload['id'] ?? $payload['transaction_id'] ?? $payload['code'] ?? '');

        // Xử lý quy đổi ngược số tiền từ cổng thanh toán về tiền tệ hệ thống
        $convertToSystemCurrency = function(float $rawAmount, string $paymentMethod) use ($settings): float {
            // Không quy đổi tỷ giá nữa, số tiền cổng gửi về cũng chính là số tiền trên hệ thống
            return $rawAmount;
        };

        $orderSyntax = trim((string) ($settings['order_transfer_syntax'] ?? 'THANHTOAN'));
        $renewalSyntax = trim((string) ($settings['renewal_transfer_syntax'] ?? 'GAHAN'));
        $depositSyntax = trim((string) ($settings['bank_transfer_syntax'] ?? 'NAPTIEN'));

        if ($amount > 0 && $orderSyntax !== '' && preg_match('/' . preg_quote($orderSyntax, '/') . '\\s*(\d+)/i', $searchContent, $matches)) {
            $order = (new \App\Models\Order())->find((int) $matches[1]);
            if ($order && ($order['payment_status'] ?? '') === 'pending') {
                $sysAmount = $convertToSystemCurrency($amount, (string) ($order['payment_method'] ?? 'vietqr'));
                $result = $orderService->processPaymentByOrderCode((string) $order['order_code'], $sysAmount, $transId);
                $this->json(['status' => $result['status'], 'message' => $result['message']], $result['status'] ? 200 : 400);
                return;
            }
        }

        if ($amount > 0 && $renewalSyntax !== '' && preg_match('/' . preg_quote($renewalSyntax, '/') . '\\s*(\d+)/i', $searchContent, $matches)) {
            $payment = $paymentModel->find((int) $matches[1]);
            if ($payment && ($payment['type'] ?? '') === 'payment' && !empty($payment['subscription_id'])) {
                $sysAmount = $convertToSystemCurrency($amount, (string) ($payment['payment_method'] ?? 'vietqr'));
                $result = $paymentService->completePaymentByCode((string) $payment['transaction_id'], $sysAmount);
                $this->json(['status' => $result, 'message' => $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại hoặc đã được xử lý.'], $result ? 200 : 400);
                return;
            }
        }

        if ($amount > 0 && $depositSyntax !== '' && preg_match('/' . preg_quote($depositSyntax, '/') . '\\s*(\d+)/i', $searchContent, $matches)) {
            $payment = $paymentModel->find((int) $matches[1]);
            if ($payment && ($payment['type'] ?? '') === 'deposit') {
                $sysAmount = $convertToSystemCurrency($amount, (string) ($payment['payment_method'] ?? 'vietqr'));
                $result = $paymentService->completePaymentByCode((string) $payment['transaction_id'], $sysAmount);
                $this->json(['status' => $result, 'message' => $result ? 'Nạp tiền vào tài khoản thành công.' : 'Xử lý mã nạp tiền thất bại hoặc đã được xử lý.'], $result ? 200 : 400);
                return;
            }
        }

        // 4. VietQR: Kiểm tra mã đơn hàng LS...
        if (!empty($searchContent) && preg_match('/LS\d+/i', $searchContent, $matches)) {
            $orderCode = strtoupper($matches[0]);
            
            if ($amount <= 0 && preg_match('/(?:\+|KH:\s*|TIEN:\s*|^)(\d+(?:\.\d+)?)/i', $searchContent, $amtMatches)) {
                $amount = (float)$amtMatches[1];
            }

            $sysAmount = $convertToSystemCurrency($amount, 'vietqr');
            $result = $orderService->processPaymentByOrderCode($orderCode, $sysAmount, $transId ?: $orderCode);
            $this->json(['status' => $result['status'], 'message' => $result['message']], $result['status'] ? 200 : 400);
            return;
        }

        // 5. Thanh toán gia hạn subscription REN...
        if (!empty($searchContent) && preg_match('/REN\d+/i', $searchContent, $matches)) {
            $transCode = strtoupper($matches[0]);
            $paymentMethod = 'vietqr';
            if (method_exists($paymentModel, 'findByTransactionId')) {
                $paymentInfo = $paymentModel->findByTransactionId($transCode);
                if ($paymentInfo) $paymentMethod = $paymentInfo['payment_method'] ?? 'vietqr';
            }
            $sysAmount = $convertToSystemCurrency($amount, $paymentMethod);
            $result = $paymentService->completePaymentByCode($transCode, $sysAmount);

            if ($result) {
                $this->json(['status' => true, 'message' => 'Gia hạn gói dịch vụ thành công.']);
            } else {
                $this->json(['status' => false, 'message' => 'Xử lý giao dịch gia hạn thất bại hoặc đã được xử lý.'], 400);
            }
            return;
        }

        // 6. VietQR: Kiểm tra mã nạp tiền DEP...
        if (!empty($content) && preg_match('/DEP\d+/i', $content, $matches)) {
            $transCode = strtoupper($matches[0]);
            $paymentMethod = 'vietqr';
            if (method_exists($paymentModel, 'findByTransactionId')) {
                $paymentInfo = $paymentModel->findByTransactionId($transCode);
                if ($paymentInfo) $paymentMethod = $paymentInfo['payment_method'] ?? 'vietqr';
            }
            $sysAmount = $convertToSystemCurrency($amount, $paymentMethod);
            $result = $paymentService->completePaymentByCode($transCode, $sysAmount);

            if ($result) {
                $this->json(['status' => true, 'message' => 'Nạp tiền vào tài khoản thành công.']);
            } else {
                $this->json(['status' => false, 'message' => 'Xử lý mã nạp tiền thất bại hoặc đã được xử lý.'], 400);
            }
            return;
        }

        // 7. WeChat Pay / VietQR: Tự động bóc tách số tiền từ nội dung thông báo
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

        // 8. Khớp đơn tự động WeChat Pay theo số tiền
        $sysAmount = $convertToSystemCurrency($amount, 'wechat');
        $result = $orderService->processPaymentByAmount($sysAmount, $transId);
        $this->json(['status' => $result['status'], 'message' => $result['message']], $result['status'] ? 200 : 404);
    }
}