<?php

namespace App\Services;

use App\Models\NodeTask;

/**
 * Điểm duy nhất sinh task gửi xuống Script VPS (menu-singbox-vvc).
 *
 * Hợp đồng 4 action: add_user | delete_user | disable_user | enable_user
 * (không còn toggle_user — xem docs/task-contract.md)
 */
class NodeTaskService
{
    /** Lý do disable hợp lệ (contract mục 2.2) */
    public const REASONS_DISABLE = ['expired', 'data_exceeded', 'admin', 'cancelled', 'device_limit'];

    /** Lý do enable/mở lại hợp lệ (contract mục 2.2) */
    public const REASONS_ENABLE = ['monthly_reset', 'renewed', 'lock_expired', 'admin'];

    private NodeTask $tasks;

    public function __construct(?NodeTask $tasks = null)
    {
        $this->tasks = $tasks ?? new NodeTask();
    }

    /**
     * Tạo/cập nhật user trên VPS. Luôn mang status tường minh (active|disabled)
     * để script không âm thầm bật lại user đang khóa.
     */
    public function addUser(
        int|array $groupIds,
        string $username,
        string $uuid,
        int $transferEnable,
        string $endDate,
        string $status = 'active',
        ?int $maxDevices = null
    ): void {
        $payload = $this->buildAddUserPayload($username, $uuid, $transferEnable, $endDate, $status, $maxDevices);
        if ($payload === null) {
            return;
        }

        $this->tasks->createTasksForGroup($groupIds, 'add_user', $payload);
    }

    /**
     * Gửi add_user tới đúng 1 server (đồng bộ thủ công từ trang quản trị server).
     */
    public function addUserToServer(
        int $serverId,
        string $username,
        string $uuid,
        int $transferEnable,
        string $endDate,
        string $status = 'active',
        ?int $maxDevices = null
    ): void {
        if ($serverId <= 0) {
            return;
        }

        $payload = $this->buildAddUserPayload($username, $uuid, $transferEnable, $endDate, $status, $maxDevices);
        if ($payload === null) {
            return;
        }

        $this->tasks->create([
            'server_id' => $serverId,
            'action'    => 'add_user',
            'payload'   => $payload
        ]);
    }

    private function buildAddUserPayload(
        string $username,
        string $uuid,
        int $transferEnable,
        string $endDate,
        string $status,
        ?int $maxDevices
    ): ?array {
        if ($username === '' || $uuid === '') {
            return null;
        }

        $payload = [
            'username'       => $username,
            'uuid'           => $uuid,
            'transfer_enable'=> max(0, $transferEnable),
            'end_date'       => $endDate,
            'status'         => $status === 'disabled' ? 'disabled' : 'active',
        ];
        if ($maxDevices !== null) {
            $payload['max_devices'] = max(1, $maxDevices);
        }

        return $payload;
    }

    /** Xóa vĩnh viễn user trên VPS */
    public function deleteUser(int|array $groupIds, string $username, string $reason = 'admin'): void
    {
        if ($username === '') {
            return;
        }

        $this->tasks->createTasksForGroup($groupIds, 'delete_user', [
            'username' => $username,
            'reason'   => $this->normalizeReason($reason, self::REASONS_DISABLE),
        ]);
    }

    /**
     * Tắt mạng user. Nếu $lockSeconds > 0 thì script cam kết tự mở lại
     * sau đúng ngần ấy giây (khóa tạm — contract mục 3).
     */
    public function disableUser(int|array $groupIds, string $username, string $reason, ?int $lockSeconds = null): void
    {
        if ($username === '') {
            return;
        }

        $payload = [
            'username' => $username,
            'reason'   => $this->normalizeReason($reason, self::REASONS_DISABLE),
        ];

        $lockSeconds = $lockSeconds !== null ? max(0, $lockSeconds) : 0;
        if ($lockSeconds > 0) {
            $payload['lock_seconds'] = $lockSeconds;
            $payload['locked_until'] = date('c', time() + $lockSeconds); // ISO-8601, timezone PHP (+07:00)
        }

        $this->tasks->createTasksForGroup($groupIds, 'disable_user', $payload);
    }

    /** Mở lại mạng user (hủy luôn khóa tạm đang chờ nếu có) */
    public function enableUser(int|array $groupIds, string $username, string $reason): void
    {
        if ($username === '') {
            return;
        }

        $this->tasks->createTasksForGroup($groupIds, 'enable_user', [
            'username' => $username,
            'reason'   => $this->normalizeReason($reason, self::REASONS_ENABLE),
        ]);
    }

    private function normalizeReason(string $reason, array $allowed): string
    {
        $reason = strtolower(trim($reason));
        return in_array($reason, $allowed, true) ? $reason : 'admin';
    }
}
