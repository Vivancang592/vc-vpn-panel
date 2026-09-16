<?php

namespace App\Models;

class ReferralCommission extends BaseModel
{
    protected string $table = 'vc_referral_commissions';

    /**
     * Lấy danh sách hoa hồng giới thiệu kèm thông tin chi tiết người giới thiệu, người mua và đơn hàng
     */
    public function allWithDetails(): array
    {
        $stmt = self::$db->prepare("
            SELECT rc.*, 
                   u1.username AS referrer_username, u1.email AS referrer_email,
                   u2.username AS referred_username, u2.email AS referred_email,
                   o.order_code, o.total_amount AS order_total
            FROM `{$this->table}` rc
            LEFT JOIN `vc_users` u1 ON rc.referrer_id = u1.id
            LEFT JOIN `vc_users` u2 ON rc.referred_user_id = u2.id
            LEFT JOIN `vc_orders` o ON rc.order_id = o.id
            ORDER BY rc.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}