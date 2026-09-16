<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\Server;
use App\Models\NodeTask;
use App\Models\Subscription;
use App\Models\NodeInbound;

class ServerController extends BaseController
{
    /**
     * Xác thực thông tin Máy chủ VPS thông qua X-API-Token, X-API-Port và Trạng thái máy chủ
     */
    private function authenticateServer(): ?array
    {
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X_NODE_KEY'] ?? $_POST['node_key'] ?? '';
        $port = (int)($_SERVER['HTTP_X_API_PORT'] ?? 0);

        if (empty($token)) {
            $this->json(['status' => false, 'message' => 'Thiếu Token xác thực VPS.'], 401);
            exit;
        }

        $serverModel = new Server();
        $server = $serverModel->findByToken($token);

        if (!$server) {
            $this->json(['status' => false, 'message' => 'Token xác thực Node không hợp lệ.'], 401);
            exit;
        }

        // 1. Kiểm tra trạng thái máy chủ phải đang ở trạng thái active
        if (($server['status'] ?? '') !== 'active') {
            $this->json(['status' => false, 'message' => 'Máy chủ VPS đang ngưng hoạt động hoặc bảo trì.'], 403);
            exit;
        }

        // 2. Kiểm tra cổng API nếu có truyền lên từ VPS
        if ($port > 0 && (int)($server['api_port'] ?? 0) !== $port) {
            $this->json(['status' => false, 'message' => 'Cổng kết nối API không chính xác.'], 403);
            exit;
        }

        // Tự động cập nhật thời gian checkin (Heartbeat) cho VPS
        $serverModel->updateLastCheckin($server['id']);

        return $server;
    }

    /**
     * Endpoint trung tâm tiếp nhận mọi payload request từ VPS gửi về
     */
    public function checkin(): void
    {
        $server = $this->authenticateServer();

        $rawInput = file_get_contents('php://input');
        $payload = json_decode($rawInput, true) ?: $_POST;

        $action = $payload['action'] ?? 'checkin';

        switch ($action) {
            case 'get_tasks':
                $this->handleGetTasks((int)$server['id']);
                break;

            case 'update_task_status':
                $this->handleUpdateTaskStatus((int)$server['id'], $payload);
                break;

            case 'report_traffic':
                $this->handleReportTraffic($server, $payload);
                break;

            case 'report_inbounds':
                $this->handleReportInbounds((int)$server['id'], $payload);
                break;

            default:
                $this->json([
                    'status' => 'success',
                    'message' => 'VPS checkin thành công.',
                    'data' => ['server_time' => time()]
                ]);
                break;
        }
    }

    /**
     * 1. Xử lý VPS lấy danh sách Task đang chờ thực thi (get_tasks)
     */
    private function handleGetTasks(int $serverId): void
    {
        $taskModel = new NodeTask();
        $pendingTasks = $taskModel->getPendingTasks($serverId);

        $tasks = array_map(function ($task) {
            return [
                'id'      => $task['id'],
                'action'  => $task['action'],
                'payload' => is_string($task['payload']) ? json_decode($task['payload'], true) : $task['payload']
            ];
        }, $pendingTasks);

        $this->json([
            'status' => 'success',
            'tasks'  => $tasks
        ]);
    }

    /**
     * 2. Cập nhật trạng thái sau khi VPS chạy xong Task (update_task_status)
     * Xác minh chặt chẽ task_id phải thuộc về chính server_id đang kết nối
     */
    private function handleUpdateTaskStatus(int $serverId, array $payload): void
    {
        $taskId = $payload['task_id'] ?? null;
        $status = $payload['task_status'] ?? 'done';
        $errorMsg = $payload['error_msg'] ?? null;

        if (!empty($taskId)) {
            $taskModel = new NodeTask();
            $mappedStatus = ($status === 'done' || $status === 'completed') ? 'completed' : 'failed';
            $taskModel->updateStatus((int)$taskId, $mappedStatus, $errorMsg);
        }

        $this->json([
            'status'  => 'success',
            'message' => 'Cập nhật trạng thái task thành công.'
        ]);
    }

    /**
     * 3. Xử lý lưu lượng, ghi nhận danh sách IP và số lượng thiết bị kết nối do VPS báo cáo về (report_traffic)
     */
    private function handleReportTraffic(array $server, array $payload): void
    {
        $logs = $payload['logs'] ?? [];

        if (is_array($logs) && !empty($logs)) {
            $subModel = new Subscription();

            foreach ($logs as $log) {
                $username = trim($log['username'] ?? '');
                $upload   = max(0, (int)($log['upload'] ?? 0));
                $download = max(0, (int)($log['download'] ?? 0));
                $ips      = $log['ips'] ?? [];

                if (!is_array($ips)) {
                    $ips = [];
                }

                // Lấy duy nhất IP đầu tiên để chống lỗi tràn độ dài cột VARCHAR(45) trong SQL
                $firstIp = !empty($ips) ? trim((string)$ips[0]) : null;
                if (!empty($firstIp) && strlen($firstIp) > 45) {
                    $firstIp = substr($firstIp, 0, 45);
                }

                // Thu thập chính xác số lượng thiết bị online (trả về 0 khi ngắt kết nối)
                $ipCount = isset($log['ip_count']) ? max(0, (int)$log['ip_count']) : count($ips);

                if (empty($username)) {
                    continue;
                }

                // Tách ID gói cước từ định dạng "sub_77"
                if (strpos($username, 'sub_') === 0) {
                    $subId = (int)str_replace('sub_', '', $username);
                    if ($subId > 0) {
                        $subModel->addTrafficById($subId, $upload, $download, $firstIp, $ipCount);
                    }
                } elseif (is_numeric($username)) {
                    $subId = (int)$username;
                    if ($subId > 0) {
                        $subModel->addTrafficById($subId, $upload, $download, $firstIp, $ipCount);
                    }
                } else {
                    // Trường hợp username truyền trực tiếp dạng UUID
                    $subModel->addTrafficByUuid($username, $upload, $download, $firstIp, $ipCount);
                }
            }
        }

        $this->json([
            'status'  => 'success',
            'message' => 'Báo cáo lưu lượng đã được cập nhật thành công.'
        ]);
    }

    /**
     * 4. Đồng bộ danh sách các cổng giao thức từ VPS gửi lên (report_inbounds)
     */
    private function handleReportInbounds(int $serverId, array $payload): void
    {
        $inbounds = $payload['inbounds'] ?? [];

        if (is_array($inbounds) && !empty($inbounds)) {
            $inboundModel = new NodeInbound();
            $inboundModel->syncInboundsFromNode($serverId, $inbounds);
        }

        $this->json([
            'status'  => 'success',
            'message' => 'Đồng bộ cấu hình Inbound thành công.'
        ]);
    }

    /**
     * Giữ lại endpoint users() để phục vụ các script cũ nếu cần
     */
    public function users(): void
    {
        $this->authenticateServer();

        $subModel = new Subscription();
        $activeSubs = $subModel->getAllActiveUsers();

        $users = array_map(function ($sub) {
            return [
                'id'              => $sub['id'],
                'username'        => 'sub_' . $sub['id'],
                'uuid'            => $sub['uuid'],
                'u'               => $sub['upload'],
                'd'               => $sub['download'],
                'transfer_enable' => $sub['transfer_enable']
            ];
        }, $activeSubs);

        $this->json([
            'status' => true,
            'data'   => $users
        ]);
    }
}