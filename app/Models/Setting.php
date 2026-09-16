<?php

namespace App\Models;

class Setting extends BaseModel
{
    protected string $table = 'vc_settings';

    /**
     * Lấy giá trị cấu hình theo key kèm giá trị mặc định
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->getByKey($key);
        return ($value !== null && $value !== '') ? $value : $default;
    }

    /**
     * Lấy tất cả cài đặt dưới dạng mảng key-value
     */
    public function getAllAsKeyValue(): array
    {
        $stmt = self::$db->prepare("SELECT `setting_key`, `setting_value` FROM `{$this->table}`");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        
        $settings = [];
        foreach ($rows as $row) {
            if (isset($row['setting_key'])) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return $settings;
    }

    /**
     * Lấy giá trị cấu hình theo key
     */
    public function getByKey(string $key): ?string
    {
        $stmt = self::$db->prepare("SELECT `setting_value` FROM `{$this->table}` WHERE `setting_key` = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['setting_value'] ?? null;
    }

    /**
     * Lưu hoặc cập nhật một cấu hình
     */
    public function setByKey(string $key, ?string $value): bool
    {
        $stmt = self::$db->prepare("
            INSERT INTO `{$this->table}` (`setting_key`, `setting_value`) 
            VALUES (:key, :value) 
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
        ");
        return $stmt->execute(['key' => $key, 'value' => $value]);
    }
}