<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Setting;

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

        // 2. Ghi Log bảo mật vào storage/logs/webhook.log
        $logFile = __DIR__ . '/../../../storage/logs/webhook.log';
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
        $paymentModel   = new Payment();
        $orderModel     = new Order();
        $settings       = (new Setting())->getAllAsKeyValue();
        $config         = require __DIR__ . '/../../../config/app.php';

        // 3. Bảo mật: Bắt buộc kiểm tra API Key / Secret Token (SePay, MacroDroid, v.v.)
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
        $validKeys = array_values(array_unique(array_filter([
            trim((string)($config['macrodroid_secret'] ?? '')),
            trim((string)($config['sepay_api_key'] ?? '')),
            trim((string)getenv('SEPAY_API_KEY')),
            trim((string)getenv('MACRODROID_SECRET')),
            trim((string)($settings['sepay_api_key'] ?? '')),
            trim((string)($settings['payment_api_key'] ?? '')),
            trim((string)($settings['webhook_api_key'] ?? '')),
            trim((string)($settings['macrodroid_secret'] ?? '')),
        ])));

        // Nếu hệ thống có cấu hình key hoặc yêu cầu bảo mật, bắt buộc phải khớp key
        if (empty($validKeys)) {
            $respond(false, 'Hệ thống chưa cấu hình Secret/API Key cho Webhook.', 403);
            return;
        }

        $isAuthenticated = false;
        foreach ($validKeys as $validKey) {
            if ($providedKey !== '' && hash_equals($validKey, $providedKey)) {
                $isAuthenticated = true;
                break;
            }
        }

        if (!$isAuthenticated) {
            $respond(false, 'Mã xác thực Webhook (API Key / Secret) không hợp lệ.', 401);
            return;
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

        $orderSyntax   = trim((string) ($settings['order_transfer_syntax'] ?? 'THANHTOAN'));
        $renewalSyntax = trim((string) ($settings['renewal_transfer_syntax'] ?? 'GAHAN'));
        $depositSyntax = trim((string) ($settings['bank_transfer_syntax'] ?? 'NAPTIEN'));

        // 4.1 Khớp đơn hàng theo Order ID (THANHTOAN{order_id}, NAPTIEN{order_id}, VCTT{order_id}, TT{order_id}, DH{order_id}, NAP{order_id})
        $orderPrefixes = array_unique(array_filter([$orderSyntax, $depositSyntax, 'VCTT', 'THANHTOAN', 'NAPTIEN', 'TT', 'DH', 'NAP']));
        $orderPattern = '/\b(?:' . implode('|', array_map(fn($p) => preg_quote($p, '/'), $orderPrefixes)) . ')\s*0*(\d+)\b/i';

        if ($amount > 0 && preg_match($orderPattern, $searchContent, $matches)) {
            $orderId = (int) $matches[1];
            $order = $orderModel->find($orderId);
            
            if (!$order) {
                // Thử tìm trong bảng Payment dự phòng
                $payment = $paymentModel->find($orderId);
                if ($payment && ($payment['status'] ?? '') === 'pending') {
                    $expectedAmount = (float) ($payment['amount'] ?? 0);
                    if (abs($amount - $expectedAmount) <= 1.0) {
                        $result = $paymentService->completePaymentByCode((string) ($payment['transfer_content'] ?? $payment['transaction_id']), $amount);
                        $respond($result, $result ? 'Xử lý giao dịch thanh toán thành công.' : 'Xử lý thất bại.', $result ? 200 : 400);
                        return;
                    }
                }
                $respond(false, 'Không tìm thấy đơn hàng #' . $orderId . ' trong hệ thống.', 404);
                return;
            }

            $orderStatus = $order['payment_status'] ?? $order['status'] ?? '';
            if ($orderStatus === 'completed') {
                $respond(true, 'Đơn hàng #' . $orderId . ' (' . ($order['order_code'] ?? '') . ') đã được xử lý trước đó.', 200);
                return;
            }

            if ($orderStatus !== 'pending') {
                $respond(false, 'Đơn hàng #' . $orderId . ' không ở trạng thái chờ thanh toán (trạng thái: ' . $orderStatus . ').', 400);
                return;
            }

            if ((int) ($order['subscription_id'] ?? 0) > 0) {
                $result = $paymentService->completeRenewalOrder(
                    $orderId,
                    $amount,
                    $transId
                );
                $respond(
                    $result,
                    $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại.',
                    $result ? 200 : 400
                );
                return;
            }

            $expectedAmount = (float)($order['total_amount'] ?? $order['final_amount'] ?? $order['price'] ?? 0);
            if (abs($amount - $expectedAmount) > 1.0) {
                $respond(false, 'Số tiền chuyển khoản (' . number_format($amount, 0, ',', '.') . ' đ) không khớp với số tiền đơn hàng #' . $orderId . ' (' . number_format($expectedAmount, 0, ',', '.') . ' đ).', 400);
                return;
            }

            $result = $orderService->processPaymentByOrderCode((string) $order['order_code'], $amount, $transId);
            $respond($result['status'], $result['message'], $result['status'] ? 200 : 400);
            return;
        }

        // 4.2 Khớp gia hạn gói theo cú pháp (GAHAN{order_id}, GH{order_id})
        $renewalPrefixes = array_unique(array_filter([$renewalSyntax, 'GAHAN', 'GH']));
        $renewalPattern = '/\b(?:' . implode('|', array_map(fn($p) => preg_quote($p, '/'), $renewalPrefixes)) . ')\s*0*(\d+)\b/i';

        if ($amount > 0 && preg_match($renewalPattern, $searchContent, $matches)) {
            $referenceId = (int) $matches[1];
            $renewalOrder = $orderModel->find($referenceId);

            if ((int) ($renewalOrder['subscription_id'] ?? 0) > 0) {
                $result = $paymentService->completeRenewalOrder(
                    $referenceId,
                    $amount,
                    $transId
                );
                $respond(
                    $result,
                    $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại.',
                    $result ? 200 : 400
                );
                return;
            }

            $payment = $paymentModel->findPendingRenewalByOrderId($referenceId);

            if ($payment === null) {
                $payment = $paymentModel->findSuccessfulByOrderId($referenceId);
            }

            // Các giao dịch được tạo trước khi chuyển sang mã đơn vẫn dùng payment ID.
            if ($payment === null) {
                $payment = $paymentModel->find($referenceId);
            }

            if ($payment && ($payment['type'] ?? '') === 'payment' && !empty($payment['subscription_id'])) {
                $paymentId = (int) ($payment['id'] ?? 0);
                if (($payment['status'] ?? '') === 'success') {
                    $respond(true, 'Giao dịch gia hạn #' . $paymentId . ' đã được xử lý trước đó.', 200);
                    return;
                }
                if (($payment['status'] ?? '') !== 'pending') {
                    $respond(false, 'Giao dịch gia hạn #' . $paymentId . ' không ở trạng thái chờ thanh toán.', 400);
                    return;
                }
                $expectedAmount = (float) ($payment['amount'] ?? 0);
                if (abs($amount - $expectedAmount) > 1.0) {
                    $respond(false, 'Số tiền chuyển khoản (' . number_format($amount, 0, ',', '.') . ' đ) không khớp với số tiền gia hạn #' . $paymentId . ' (' . number_format($expectedAmount, 0, ',', '.') . ' đ).', 400);
                    return;
                }
                $result = $paymentService->completePaymentByCode((string) ($payment['transfer_content'] ?? $payment['transaction_id']), $amount);
                $respond($result, $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại.', $result ? 200 : 400);
                return;
            }
        }

        // 4.4 Kiểm tra trực tiếp theo mã đơn hàng LS... / ORD...
        if (!empty($searchContent) && preg_match('/\b(?:LS|ORD)\d+\b/i', $searchContent, $matches)) {
            $orderCode = strtoupper($matches[0]);
            $result = $orderService->processPaymentByOrderCode($orderCode, $amount, $transId);
            $respond($result['status'], $result['message'], $result['status'] ? 200 : 400);
            return;
        }

        // 4.5 Kiểm tra theo mã giao dịch gia hạn REN...
        if (!empty($searchContent) && preg_match('/\bREN\d+\b/i', $searchContent, $matches)) {
            $transCode = strtoupper($matches[0]);
            $result = $paymentService->completePaymentByCode($transCode, $amount);
            $respond($result, $result ? 'Gia hạn gói dịch vụ thành công.' : 'Xử lý giao dịch gia hạn thất bại hoặc đã được xử lý.', $result ? 200 : 400);
            return;
        }

        // 4.6 Kiểm tra theo mã giao dịch nạp tiền DEP...
        if (!empty($searchContent) && preg_match('/\bDEP\d+\b/i', $searchContent, $matches)) {
            $transCode = strtoupper($matches[0]);
            $result = $paymentService->completePaymentByCode($transCode, $amount);
            $respond($result, $result ? 'Nạp tiền vào tài khoản thành công.' : 'Xử lý mã nạp tiền thất bại hoặc đã được xử lý.', $result ? 200 : 400);
            return;
        }

        // 5. Từ chối nếu nội dung không khớp bất kỳ đơn hàng nào
        $respond(false, 'Nội dung chuyển khoản không khớp với bất kỳ đơn hàng hoặc giao dịch nào: ' . ($content ?: $searchContent), 400);
    }
}