<?php

namespace App\Services;

use App\Models\Server;
use App\Models\NodeTask;

class ServerService
{
    /**
     * Kiểm tra trạng thái máy chủ Node (Ping / Healthcheck)
     */
    public function checkServerStatus(string $ip, int $port = 80, int $timeout = 3): bool
    {
        $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Đồng bộ tác vụ xuống máy chủ Node
     */
    public function createNodeTask(int $serverId, string $action, array $payload = []): bool
    {
        $taskModel = new NodeTask();
        return $taskModel->create([
            'server_id' => $serverId,
            'action' => $action,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Cập nhật thời gian phản hồi gần nhất của Node
     */
    public function updateNodeHeartbeat(int $serverId): bool
    {
        $serverModel = new Server();
        return $serverModel->update($serverId, [
            'last_checkin' => date('Y-m-d H:i:s'),
            'status' => 'online'
        ]);
    }
}