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

        $respond = function(bool $success, string $message, int $statusCode = 200) {
            $this->json([
                'success' => $success,
                'status'  => $success,
                'message' => $message
            ], $statusCode);
        };

        if (empty($payload)) {
            $respond(false, 'Dữ liệu Webhook không hợp lệ.', 400);
            return;
        }

        $orderService   = new OrderService();
        $paymentService = new PaymentService();
        $paymentModel   = new \App\Models\Payment();
        $orderModel     = new \App\Models\Order();
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
                $respond(false, 'API Key hoặc mã xác thực Webhook không hợp lệ.', 403);
                return;
            }
        }

        // Bỏ qua nếu là giao dịch tiền ra từ SePay (transferType != in)
        if (isset($payload['transferType']) && strtolower((string)$payload['transferType']) !== 'in') {
            $respond(true, 'Bỏ qua giao dịch không phải tiền vào.', 200);
            return;
        }

        $content = trim((string)($payload['content'] ?? $payload['description'] ?? ''));
        $searchContent = trim($content . ' ' . (string)($payload['description'] ?? '') . ' ' . (string)($payload['code'] ?? ''));
        $amount  = (float)($payload['transferAmount'] ?? $payload['amount'] ?? 0);
        $transId = (string)($payload['referenceCode'] ?? $payload['id'] ?? $payload['transaction_id'] ?? $payload['code'] ?? '');
        $isBankGateway = !empty($payload['gateway']) || !empty($payload['accountNumber']) || isset($payload['transferAmount']) || isset($payload['referenceCode']);

        // Xử lý quy đổi ngược số tiền từ cổng thanh toán về tiền tệ hệ thống
        $convertToSystemCurrency = function(float $rawAmount, string $paymentMethod) use ($settings): float {
            return $rawAmount;
        };

        $orderSyntax = trim((string) ($settings['order_transfer_syntax'] ?? 'THANHTOAN'));
        $renewalSyntax = trim((string) ($settings['renewal_transfer_syntax'] ?? 'GAHAN'));
        $depositSyntax = trim((string) ($settings['bank_transfer_syntax'] ?? 'NAPTIEN'));

        // 4.1 Khớp đơn hàng theo transfer_content, tiền tố cấu hình hoặc các tiền tố chuẩn (VCTT, THANHTOAN, TT, DH, VC)
        $orderPrefixes = array_unique(array_filter([$orderSyntax, 'VCTT', 'THANHTOAN', 'TT', 'DH', 'VC']));
        $orderPattern = '/(?:' . implode('|', array_map(fn($p) => preg_quote($p, '/'), $orderPrefixes)) . ')\s*0*(\d+)/i';

        if ($amount > 0 && preg_match($orderPattern, $searchContent, $matches)) {
            $orderId = (int) $matches[1];
            $order = $orderModel->find($orderId);
            
            if (!$order) {
                $respond(false, 'Không tìm thấy đơn hàng #' . $orderId . ' trong hệ thống.', 404);
                return;
            }

            // Chỉ kích hoạt nếu đơn hàng đang ở trạng thái pending (chờ thanh toán)
            $orderStatus = $order['payment_status'] ?? $order['status'] ?? '';
            if ($orderStatus !== 'pending') {
                $respond(true, 'Đơn hàng #' . $orderId . ' (' . ($order['order_code'] ?? '') . ') không ở trạng thái chờ thanh toán (trạng thái hiện tại: ' . $orderStatus . ').', 200);
                return;
            }

            // Kiểm tra số tiền chuyển khoản phải khớp tuyệt đối với tổng tiền đơn hàng
            $expectedAmount = (float)($order['total_amount'] ?? $order['final_amount'] ?? $order['price'] ?? 0);
            $sysAmount = $convertToSystemCurrency($amount, (string) ($order['payment_method'] ?? 'vietqr'));

            if (abs($sysAmount - $expectedAmount) > 0.001) {
                $respond(false, 'Số tiền chuyển khoản (' . number_format($sysAmount, 0, ',', '.') . ' VNĐ) không khớp chính xác với số tiền đơn hàng #' . $orderId . ' (' . number_format($expectedAmount, 0, ',', '.') . ' VNĐ).', 400);
                return;
            }

            // Kích hoạt đơn hàng và lưu lịch sử thanh toán
            $result = $orderService->processPaymentByOrderCode((string) $order['order_code'], $sysAmount, $transId);
            $respond($result['status'], $result['message'], $result['status'] ? 200 : 400);
            return;
        }

        // 4.2 Khớp gia hạn gói theo tiền tố cấu hình hoặc GAHAN / REN
        $renewalPrefixes = array_unique(array_filter([$renewalSyntax, 'GAHAN', 'GH', 'REN']));
        $renewalPattern = '/(?:' . implode('|', array_map(fn($p) => preg_quote($p, '/'), $renewalPrefixes)) . ')\s*0*(\d+)/i';

        if ($amount > 0 && preg_match($renewalPattern, $searchContent, $matches)) {
            $payment = $paymentModel->find((int) $matches[1]);
            if ($payment && ($payment['type'] ?? '') === 'payment' && !empty($payment['subscription_id'])) {
                if (($payment['status'] ?? '') !== 'pending') {
                    $respond(true, 'Giao dịch gia hạn không ở trạng thái chờ thanh toán (trạng thái: ' . ($payment['status'] ?? '') . ').', 200);
                    return;
                }
                $sysAmount = $convertToSystemCurrency($amount, (string) ($payment['payment_method'] ?? 'vietqr'));
                $expectedAmount = (float) ($payment['amount'] ?? 0);
                if (abs($sysAmount - $expectedAmount) > 0.001) {
                    $respond(false, 'Số tiền chuyển khoản (' . number_format($sysAmount, 0, ',', '.') . ' VNĐ) không khớp chính xác với số tiền giao dịch gia hạn #' . $matches[1] . ' (' . number_format($expectedAmount, 0, ',', '.') . ' VNĐ).', 400);
                    return;
                }
                $result = $paymentService->completePaymentByCode((string) ($payment['transfer_content'] ?? $payment['transaction_id']), $sysAmount);
                $respond($result, $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại.', $result ? 200 : 400);
                return;
            }
        }

        // 4.3 Khớp nạp tiền theo tiền tố cấu hình hoặc NAPTIEN / NAP / DEP
        $depositPrefixes = array_unique(array_filter([$depositSyntax, 'NAPTIEN', 'NAP', 'DEP']));
        $depositPattern = '/(?:' . implode('|', array_map(fn($p) => preg_quote($p, '/'), $depositPrefixes)) . ')\s*0*(\d+)/i';

        if ($amount > 0 && preg_match($depositPattern, $searchContent, $matches)) {
            $payment = $paymentModel->find((int) $matches[1]);
            if ($payment && ($payment['type'] ?? '') === 'deposit') {
                if (($payment['status'] ?? '') !== 'pending') {
                    $respond(true, 'Mã nạp tiền không ở trạng thái chờ thanh toán (trạng thái: ' . ($payment['status'] ?? '') . ').', 200);
                    return;
                }
                $sysAmount = $convertToSystemCurrency($amount, (string) ($payment['payment_method'] ?? 'vietqr'));
                $expectedAmount = (float) ($payment['amount'] ?? 0);
                if (abs($sysAmount - $expectedAmount) > 0.001) {
                    $respond(false, 'Số tiền chuyển khoản (' . number_format($sysAmount, 0, ',', '.') . ' VNĐ) không khớp chính xác với số tiền nạp #' . $matches[1] . ' (' . number_format($expectedAmount, 0, ',', '.') . ' VNĐ).', 400);
                    return;
                }
                $result = $paymentService->completePaymentByCode((string) ($payment['transfer_content'] ?? $payment['transaction_id']), $sysAmount);
                $respond($result, $result ? 'Nạp tiền vào tài khoản thành công.' : 'Xử lý mã nạp tiền thất bại.', $result ? 200 : 400);
                return;
            }
        }

        // 4.4 VietQR / SePay: Kiểm tra trực tiếp theo mã đơn hàng LS... / ORD...
        if (!empty($searchContent) && preg_match('/(?:LS|ORD)\d+/i', $searchContent, $matches)) {
            $orderCode = strtoupper($matches[0]);
            
            if ($amount <= 0 && preg_match('/(?:\+|KH:\s*|TIEN:\s*|^)(\d+(?:\.\d+)?)/i', $searchContent, $amtMatches)) {
                $amount = (float)$amtMatches[1];
            }

            $sysAmount = $convertToSystemCurrency($amount, 'vietqr');
            $result = $orderService->processPaymentByOrderCode($orderCode, $sysAmount, $transId);
            $respond($result['status'], $result['message'], $result['status'] ? 200 : 400);
            return;
        }

        // 4.5 Thanh toán gia hạn subscription theo mã giao dịch REN...
        if (!empty($searchContent) && preg_match('/REN\d+/i', $searchContent, $matches)) {
            $transCode = strtoupper($matches[0]);
            $paymentMethod = 'vietqr';
            if (method_exists($paymentModel, 'findByTransactionId')) {
                $paymentInfo = $paymentModel->findByTransactionId($transCode);
                if ($paymentInfo) $paymentMethod = $paymentInfo['payment_method'] ?? 'vietqr';
            }
            $sysAmount = $convertToSystemCurrency($amount, $paymentMethod);
            $result = $paymentService->completePaymentByCode($transCode, $sysAmount);
            $respond($result, $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại hoặc đã được xử lý.', $result ? 200 : 400);
            return;
        }

        // 4.6 VietQR: Kiểm tra mã nạp tiền DEP...
        if (!empty($searchContent) && preg_match('/DEP\d+/i', $searchContent, $matches)) {
            $transCode = strtoupper($matches[0]);
            $paymentMethod = 'vietqr';
            if (method_exists($paymentModel, 'findByTransactionId')) {
                $paymentInfo = $paymentModel->findByTransactionId($transCode);
                if ($paymentInfo) $paymentMethod = $paymentInfo['payment_method'] ?? 'vietqr';
            }
            $sysAmount = $convertToSystemCurrency($amount, $paymentMethod);
            $result = $paymentService->completePaymentByCode($transCode, $sysAmount);
            $respond($result, $result ? 'Nạp tiền vào tài khoản thành công.' : 'Xử lý mã nạp tiền thất bại hoặc đã được xử lý.', $result ? 200 : 400);
            return;
        }

        // 5. Tuyệt đối không tự ý duyệt đơn nếu nội dung chuyển khoản không khớp bất kỳ cú pháp nào
        $respond(false, 'Nội dung chuyển khoản không khớp với bất kỳ đơn hàng hoặc giao dịch nào trong hệ thống: ' . ($content ?: $searchContent), 400);
    }
}