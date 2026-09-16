<?php

namespace App\Models;

class AccessLog extends BaseModel
{
    protected string $table = 'vc_access_logs';

    /**
     * Ghi nhận phiên truy cập thành công cùng dữ liệu nguồn truy cập đầu tiên.
     * Lỗi ghi log không được làm gián đoạn thao tác đăng nhập hoặc đăng xuất.
     */
    public function record(int $userId, string $action, string $ipAddress, string $userAgent, array $attribution = []): bool
    {
        try {
            return $this->create([
                'user_id'       => $userId,
                'action'        => substr($action, 0, 50),
                'source'        => $this->nullableString($attribution['source'] ?? 'direct', 100) ?? 'direct',
                'referrer_host' => $this->nullableString($attribution['referrer_host'] ?? null, 255),
                'landing_path'  => $this->nullableString($attribution['landing_path'] ?? null, 255),
                'utm_source'    => $this->nullableString($attribution['utm_source'] ?? null, 100),
                'utm_medium'    => $this->nullableString($attribution['utm_medium'] ?? null, 100),
                'utm_campaign'  => $this->nullableString($attribution['utm_campaign'] ?? null, 150),
                'ip_address'    => substr($ipAddress, 0, 45),
                'user_agent'    => $this->nullableString($userAgent, 1000),
                'created_at'    => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $exception) {
            error_log('Không thể ghi Access Log: ' . $exception->getMessage());
            return false;
        }
    }

    /**
     * Lấy toàn bộ nhật ký truy cập kèm thông tin tài khoản
     */
    public function allWithUser(): array
    {
        $stmt = self::$db->prepare("
            SELECT al.*, u.username, u.email
            FROM `{$this->table}` al
            LEFT JOIN `vc_users` u ON al.user_id = u.id
            ORDER BY al.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : substr($value, 0, $maxLength);
    }
}
