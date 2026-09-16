<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\Subscription;
use App\Models\SubscriptionAccessLog;
use App\Models\NodeInbound;
use App\Models\VpnPlan;
use App\Services\VpnService;

class ClientController extends BaseController
{
    public function subscribe(): void
    {
        $uuid = trim($_GET['uuid'] ?? $_GET['token'] ?? '');

        if (empty($uuid)) {
            $this->json(['status' => false, 'message' => 'Mã đăng ký (UUID) không hợp lệ.'], 400);
            return;
        }

        $subModel = new Subscription();
        $subscription = $subModel->findByUuid($uuid);

        if (!$subscription || ($subscription['status'] ?? '') !== 'active' || strtotime($subscription['end_date']) < time()) {
            header('HTTP/1.1 403 Forbidden');
            echo "Gói đăng ký không tồn tại, đã bị khóa hoặc hết hạn.";
            exit;
        }

        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $client = $this->identifySubscriptionClient($userAgent);
        try {
            (new SubscriptionAccessLog())->record(
                (int) $subscription['id'],
                $this->getClientIp(),
                $client['app_name'],
                $client['os_name'],
                $client['request_type'],
                $userAgent
            );
        } catch (\Throwable $exception) {
            error_log('Không thể ghi nhật ký kéo subscription: ' . $exception->getMessage());
        }

        if ($client['request_type'] !== 'vpn_app') {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store, private');
            echo 'Liên kết đăng ký chỉ được kéo bằng ứng dụng VPN được hỗ trợ. Vui lòng mở bằng Karing, v2rayNG hoặc ứng dụng VPN tương thích.';
            exit;
        }

        // Lấy danh sách group_id (mảng) từ gói cước tương ứng với gói đăng ký
        $groupIds = [];
        if (class_exists('App\Models\VpnPlan') && !empty($subscription['plan_id'])) {
            $planModel = new VpnPlan();
            $plan = $planModel->find((int)$subscription['plan_id']);
            if ($plan) {
                $groupIds = json_decode($plan['group_id'] ?? '[]', true);
                if (!is_array($groupIds)) {
                    $groupIds = !empty($plan['group_id']) ? [(int)$plan['group_id']] : [];
                }
            }
        }

        // Lấy danh sách Node Inbounds đang hoạt động thuộc các nhóm máy chủ của gói cước
        $nodeInboundModel = new NodeInbound();
        $inbounds = $nodeInboundModel->getAllActiveWithServer($groupIds);

        $vpnService = new VpnService();
        $links = [];

        foreach ($inbounds as $inbound) {
            $link = $vpnService->buildLink($inbound, $uuid);
            if ($link) {
                $links[] = $link;
            }
        }

        // Nếu chưa có giao thức nào trong database hoặc không có node active
        if (empty($links)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "Chưa có giao thức nào được cấp.";
            exit;
        }

        // Trả về Header thông tin dung lượng cho App Client
        $upload = $subscription['upload'] ?? 0;
        $download = $subscription['download'] ?? 0;
        $total = $subscription['transfer_enable'] ?? 0;
        $expire = strtotime($subscription['end_date']);

        // Nhân bản node đầu tiên để tạo 3 node thông tin: Cập nhật, Hạn dùng và Dung lượng
        $baseLink = $links[0];
        $hashPos = strpos($baseLink, '#');
        $cleanLink = ($hashPos !== false) ? substr($baseLink, 0, $hashPos) : $baseLink;

        $nodeUpdate = $cleanLink . '#' . rawurlencode('Cập Nhật Thường Xuyên');
        $nodeExpire = $cleanLink . '#' . rawurlencode('HDS: ' . date('d/m/Y', $expire));

        $usedGb = round(($upload + $download) / 1073741824, 2);
        if ($total > 0) {
            $totalGb = round($total / 1073741824, 2);
            $nodeData = $cleanLink . '#' . rawurlencode("Data: {$usedGb} GB / {$totalGb} GB");
        } else {
            $nodeData = $cleanLink . '#' . rawurlencode("Data: {$usedGb} GB / KGH");
        }

        array_unshift($links, $nodeUpdate, $nodeExpire, $nodeData);

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, private');
        header("Subscription-Userinfo: upload={$upload}; download={$download}; total={$total}; expire={$expire}");

        echo base64_encode(implode("\n", $links));
        exit;
    }

    private function identifySubscriptionClient(string $userAgent): array
    {
        $normalizedUserAgent = strtolower($userAgent);
        $osName = match (true) {
            str_contains($normalizedUserAgent, 'android') => 'Android',
            str_contains($normalizedUserAgent, 'iphone'), str_contains($normalizedUserAgent, 'ipad') => 'iOS',
            str_contains($normalizedUserAgent, 'windows') => 'Windows',
            str_contains($normalizedUserAgent, 'mac os') => 'macOS',
            str_contains($normalizedUserAgent, 'linux') => 'Linux',
            default => 'Unknown'
        };

        $vpnApps = [
            'v2rayng' => 'v2rayNG',
            'karing' => 'Karing',
            'clash' => 'Clash',
            'sing-box' => 'sing-box',
            'nekobox' => 'NekoBox',
            'shadowrocket' => 'Shadowrocket',
            'hiddify' => 'Hiddify',
            'surfboard' => 'Surfboard'
        ];
        foreach ($vpnApps as $needle => $appName) {
            if (str_contains($normalizedUserAgent, $needle)) {
                return ['app_name' => $appName, 'os_name' => $osName, 'request_type' => 'vpn_app'];
            }
        }

        if (preg_match('/\b(mozilla|chrome|safari|firefox|edg|opr)\b/', $normalizedUserAgent)) {
            return ['app_name' => 'Web Browser', 'os_name' => $osName, 'request_type' => 'browser'];
        }

        return ['app_name' => $userAgent === '' ? 'Unknown' : 'Unknown Client', 'os_name' => $osName, 'request_type' => 'unknown'];
    }
}