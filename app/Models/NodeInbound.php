<?php

namespace App\Models;

class NodeInbound extends BaseModel
{
    protected string $table = 'vc_node_inbounds';

    /**
     * Đếm tổng số Inbound đang hoạt động (active)
     */
    public function countActive(): int
    {
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = 'active'");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Đếm tất cả Inbound có trong hệ thống
     */
    public function countAll(): int
    {
        $stmt = self::$db->prepare("SELECT COUNT(*) FROM `{$this->table}`");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Lấy danh sách tất cả Node Inbound đang hoạt động kèm thông tin Máy chủ (Server), hỗ trợ truyền 1 ID hoặc mảng group_ids
     */
    public function getAllActiveWithServer(int|array|null $groupId = null): array
    {
        $sql = "SELECT i.*, s.name AS server_name, s.ip_address, s.group_id 
                FROM {$this->table} i
                INNER JOIN vc_servers s ON i.server_id = s.id
                WHERE i.status = 'active' AND s.status = 'active'";

        $params = [];

        if (!empty($groupId)) {
            if (is_array($groupId)) {
                $groupIds = array_map('intval', array_filter($groupId));
                if (!empty($groupIds)) {
                    $inClause = implode(',', array_fill(0, count($groupIds), '?'));
                    $sql .= " AND s.group_id IN ({$inClause})";
                    $params = array_values($groupIds);
                }
            } else {
                $gId = (int)$groupId;
                if ($gId > 0) {
                    $sql .= " AND s.group_id = ?";
                    $params = [$gId];
                }
            }
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Đồng bộ / Cập nhật cấu hình cổng Inbound trực tiếp từ máy chủ VPS báo về
     */
    public function syncInboundsFromNode(int $serverId, array $inbounds): bool
    {
        if (empty($inbounds)) {
            return false;
        }

        foreach ($inbounds as $item) {
            $port = (int)($item['port'] ?? 0);
            if ($port <= 0) {
                continue;
            }

            $tag = $item['tag'] ?? null;
            $rawProtocol = strtolower($item['type'] ?? 'vless');

            // Chuẩn hóa tên giao thức khớp với ENUM trong CSDL
            if (str_contains($rawProtocol, 'vless')) {
                $protocol = 'vless';
            } elseif (str_contains($rawProtocol, 'vmess')) {
                $protocol = 'vmess';
            } elseif (str_contains($rawProtocol, 'hysteria') || $rawProtocol === 'hy2') {
                $protocol = 'hy2';
            } elseif (str_contains($rawProtocol, 'tuic')) {
                $protocol = 'tuic';
            } elseif (str_contains($rawProtocol, 'trojan')) {
                $protocol = 'trojan';
            } elseif (str_contains($rawProtocol, 'shadowsocks')) {
                $protocol = 'shadowsocks';
            } else {
                $protocol = 'vless';
            }

            // Phân loại mạng truyền tải (network)
            $network = 'tcp';
            if (!empty($item['grpc_service']) || str_contains($rawProtocol, 'grpc')) {
                $network = 'grpc';
            } elseif (!empty($item['ws_path']) || str_contains($rawProtocol, 'ws')) {
                $network = 'ws';
            }

            $sni         = $item['sni'] ?? null;
            $host        = $item['domain'] ?? $item['address'] ?? null;
            $path        = $item['ws_path'] ?? null;
            $publicKey   = $item['public_key'] ?? null;
            $shortId     = $item['short_id'] ?? null;
            $serviceName = $item['grpc_service'] ?? null;
            $password    = $item['password'] ?? null;
            $status      = ($item['inbound_status'] ?? 'online') === 'offline' ? 'inactive' : 'active';

            // Kiểm tra Inbound theo server_id và port đã tồn tại chưa
            $checkStmt = self::$db->prepare("SELECT id FROM `{$this->table}` WHERE `server_id` = :server_id AND `port` = :port LIMIT 1");
            $checkStmt->execute(['server_id' => $serverId, 'port' => $port]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $sql = "UPDATE `{$this->table}` SET 
                            `tag` = :tag,
                            `protocol` = :protocol,
                            `network` = :network,
                            `sni` = :sni,
                            `host` = :host,
                            `path` = :path,
                            `public_key` = :public_key,
                            `short_id` = :short_id,
                            `service_name` = :service_name,
                            `password` = :password,
                            `status` = :status
                        WHERE `id` = :id";

                $stmt = self::$db->prepare($sql);
                $stmt->execute([
                    'tag'          => $tag,
                    'protocol'     => $protocol,
                    'network'      => $network,
                    'sni'          => $sni,
                    'host'         => $host,
                    'path'         => $path,
                    'public_key'   => $publicKey,
                    'short_id'     => $shortId,
                    'service_name' => $serviceName,
                    'password'     => $password,
                    'status'       => $status,
                    'id'           => $existing['id']
                ]);
            } else {
                $sql = "INSERT INTO `{$this->table}` 
                            (`server_id`, `tag`, `port`, `protocol`, `network`, `sni`, `host`, `path`, `public_key`, `short_id`, `service_name`, `password`, `status`)
                        VALUES 
                            (:server_id, :tag, :port, :protocol, :network, :sni, :host, :path, :public_key, :short_id, :service_name, :password, :status)";

                $stmt = self::$db->prepare($sql);
                $stmt->execute([
                    'server_id'    => $serverId,
                    'tag'          => $tag,
                    'port'         => $port,
                    'protocol'     => $protocol,
                    'network'      => $network,
                    'sni'          => $sni,
                    'host'         => $host,
                    'path'         => $path,
                    'public_key'   => $publicKey,
                    'short_id'     => $shortId,
                    'service_name' => $serviceName,
                    'password'     => $password,
                    'status'       => $status
                ]);
            }
        }

        return true;
    }
}