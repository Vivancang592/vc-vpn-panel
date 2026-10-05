<?php

declare(strict_types=1);

namespace App\AI\Knowledge;

/**
 * Bộ công cụ tra cứu số liệu admin cho Trợ Lý Admin (whitelist hàm).
 *
 * Chỉ ĐỌC dữ liệu (SELECT), không ghi. Mọi tham số được ép kiểu int và
 * giới hạn phạm vi để AI không thể truy vấn ngoài phạm vi thống kê.
 * Protocol: JSON text (Kira không hỗ trợ tools param) — service layer parse
 * JSON {"tool":..., "args":...} rồi gọi dispatch().
 */
final class AdminStatsTools
{
    /** @var array<string, string> mô tả từng tool cho system prompt. */
    private const TOOLS = [
        'get_sales_stats'       => 'Doanh thu & đơn hàng theo tháng. args: {year?:int, month?:int} (mặc định tháng hiện tại).',
        'get_top_plans'         => 'Gói cước bán chạy nhất theo tháng (số đơn + doanh thu). args: {year?:int, month?:int, limit?:int 1-10}.',
        'get_customer_stats'    => 'Thống kê khách hàng (tổng, mới hôm nay/trong tháng, trạng thái). args: {}.',
        'get_subscription_stats'=> 'Thống kê gói đăng ký (đang hoạt động, sắp hết hạn, theo gói). args: {}.',
        'get_expense_summary'   => 'Tổng chi phí theo tháng và theo danh mục. args: {year?:int, month?:int}.',
    ];

    /**
     * Khối mô tả protocol nhúng vào system prompt lúc runtime.
     */
    public static function protocolBlock(): string
    {
        $lines = [
            "\n### CÔNG CỤ TRUY VẤN SỐ LIỆU ADMIN",
            'Khi cần số liệu, hãy trả lời CHỈ bằng một JSON duy nhất (không kèm text khác), đặt trong code block ```json:',
            '{"tool": "<ten_tool>", "args": {...}}',
            'Các tool hợp lệ:',
        ];
        foreach (self::TOOLS as $name => $desc) {
            $lines[] = "- {$name}: {$desc}";
        }
        $lines[] = 'Sau khi nhận kết quả do hệ thống gửi lại, hãy trả lời admin bằng tiếng Việt như bình thường (không hiện JSON).';
        return implode("\n", $lines);
    }

    /**
     * Chạy tool theo tên — trả về mảng kết quả (được service json_encode cho AI).
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function dispatch(string $tool, array $args): array
    {
        if (!array_key_exists($tool, self::TOOLS)) {
            return ['ok' => false, 'error' => 'Unknown tool: ' . $tool];
        }

        try {
            switch ($tool) {
                case 'get_sales_stats':
                    return self::salesStats(self::argInt($args, 'year'), self::argInt($args, 'month'));
                case 'get_top_plans':
                    return self::topPlans(self::argInt($args, 'year'), self::argInt($args, 'month'), self::argInt($args, 'limit', 5, 1, 10));
                case 'get_customer_stats':
                    return self::customerStats();
                case 'get_subscription_stats':
                    return self::subscriptionStats();
                case 'get_expense_summary':
                    return self::expenseSummary(self::argInt($args, 'year'), self::argInt($args, 'month'));
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'DB error: ' . $e->getMessage()];
        }

        return ['ok' => false, 'error' => 'Unknown tool: ' . $tool];
    }

    // -----------------------------------------------------------------
    // Tools
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private static function salesStats(?int $year, ?int $month): array
    {
        $year = $year ?: (int) date('Y');
        $month = $month ?: (int) date('n');
        $pdo = self::pdo();

        $stmt = $pdo->prepare(
            'SELECT
                SUM(CASE WHEN payment_status = "completed" THEN 1 ELSE 0 END) AS completed_cnt,
                SUM(CASE WHEN payment_status = "completed" THEN total_amount ELSE 0 END) AS revenue,
                SUM(CASE WHEN payment_status = "pending" THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN payment_status = "cancelled" THEN 1 ELSE 0 END) AS cancelled_cnt
             FROM vc_orders
             WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?'
        );
        $stmt->execute([$year, $month]);
        $row = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare(
            'SELECT DAY(created_at) AS d, SUM(total_amount) AS t
             FROM vc_orders
             WHERE payment_status = "completed" AND YEAR(created_at) = ? AND MONTH(created_at) = ?
             GROUP BY DAY(created_at)'
        );
        $stmt->execute([$year, $month]);
        $daily = [];
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $daily[(int) $r['d']] = (float) $r['t'];
        }

        $prev = self::shiftMonth($year, $month, -1);
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) FROM vc_orders
             WHERE payment_status = "completed" AND YEAR(created_at) = ? AND MONTH(created_at) = ?'
        );
        $stmt->execute([$prev['year'], $prev['month']]);
        $prevRevenue = (float) $stmt->fetchColumn();

        $revenue = (float) ($row['revenue'] ?? 0);
        $growth = $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1) : null;

        return [
            'ok'                => true,
            'month'             => sprintf('%04d-%02d', $year, $month),
            'revenue_vnd'       => $revenue,
            'revenue_prev_vnd'  => $prevRevenue,
            'growth_pct'        => $growth,
            'orders_completed'  => (int) ($row['completed_cnt'] ?? 0),
            'orders_pending'    => (int) ($row['pending_cnt'] ?? 0),
            'orders_cancelled'  => (int) ($row['cancelled_cnt'] ?? 0),
            'daily_vnd'         => $daily,
        ];
    }

    /** @return array<string, mixed> */
    private static function topPlans(?int $year, ?int $month, int $limit): array
    {
        $year = $year ?: (int) date('Y');
        $month = $month ?: (int) date('n');
        $pdo = self::pdo();

        $stmt = $pdo->prepare(
            'SELECT p.name, COUNT(o.id) AS orders, COALESCE(SUM(o.total_amount), 0) AS revenue
             FROM vc_orders o
             INNER JOIN vc_vpn_plans p ON p.id = o.plan_id
             WHERE o.payment_status = "completed" AND YEAR(o.created_at) = ? AND MONTH(o.created_at) = ?
             GROUP BY p.id, p.name
             ORDER BY revenue DESC, orders DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $year, \PDO::PARAM_INT);
        $stmt->bindValue(2, $month, \PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $top = [];
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $top[] = [
                'name'         => (string) $r['name'],
                'orders'       => (int) $r['orders'],
                'revenue_vnd'  => (float) $r['revenue'],
            ];
        }

        return ['ok' => true, 'month' => sprintf('%04d-%02d', $year, $month), 'top_plans' => $top];
    }

    /** @return array<string, mixed> */
    private static function customerStats(): array
    {
        $pdo = self::pdo();

        $row = $pdo->query(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS active_cnt,
                SUM(CASE WHEN status != "active" THEN 1 ELSE 0 END) AS inactive_cnt,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS this_month
             FROM vc_users WHERE role = "user"'
        )->fetch() ?: [];

        return [
            'ok'               => true,
            'total_users'      => (int) ($row['total'] ?? 0),
            'active'           => (int) ($row['active_cnt'] ?? 0),
            'inactive'         => (int) ($row['inactive_cnt'] ?? 0),
            'new_today'        => (int) ($row['today'] ?? 0),
            'new_this_month'   => (int) ($row['this_month'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private static function subscriptionStats(): array
    {
        $pdo = self::pdo();

        $row = $pdo->query(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = "active" AND end_date > NOW() THEN 1 ELSE 0 END) AS active_cnt,
                SUM(CASE WHEN status = "active" AND end_date > NOW() AND end_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS expiring_7d,
                SUM(CASE WHEN status = "expired" OR (end_date <= NOW() AND status != "active") THEN 1 ELSE 0 END) AS expired_cnt
             FROM vc_subscriptions'
        )->fetch() ?: [];

        $stmt = $pdo->query(
            'SELECT p.name, COUNT(*) AS cnt
             FROM vc_subscriptions s
             INNER JOIN vc_vpn_plans p ON p.id = s.plan_id
             WHERE s.status = "active" AND s.end_date > NOW()
             GROUP BY p.id, p.name ORDER BY cnt DESC LIMIT 10'
        );
        $byPlan = [];
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $byPlan[(string) $r['name']] = (int) $r['cnt'];
        }

        return [
            'ok'                  => true,
            'total'               => (int) ($row['total'] ?? 0),
            'active'              => (int) ($row['active_cnt'] ?? 0),
            'expiring_within_7d'  => (int) ($row['expiring_7d'] ?? 0),
            'expired'             => (int) ($row['expired_cnt'] ?? 0),
            'active_by_plan'      => $byPlan,
        ];
    }

    /** @return array<string, mixed> */
    private static function expenseSummary(?int $year, ?int $month): array
    {
        $year = $year ?: (int) date('Y');
        $month = $month ?: (int) date('n');
        $pdo = self::pdo();

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt
             FROM vc_expenses
             WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?'
        );
        $stmt->execute([$year, $month]);
        $row = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare(
            'SELECT category, COALESCE(SUM(amount), 0) AS total
             FROM vc_expenses
             WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?
             GROUP BY category ORDER BY total DESC'
        );
        $stmt->execute([$year, $month]);
        $byCategory = [];
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $byCategory[(string) ($r['category'] ?: 'Khác')] = (float) $r['total'];
        }

        return [
            'ok'             => true,
            'month'          => sprintf('%04d-%02d', $year, $month),
            'total_vnd'      => (float) ($row['total'] ?? 0),
            'entries'        => (int) ($row['cnt'] ?? 0),
            'by_category_vnd'=> $byCategory,
        ];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * PDO dùng chung — gọi qua model cụ thể (BaseModel là abstract,
     * new static() trong getPdo() sẽ ném lỗi nếu gọi trực tiếp).
     */
    private static function pdo(): \PDO
    {
        return \App\Models\Order::getPdo();
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function argInt(array $args, string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
    {
        if (!array_key_exists($key, $args) || $args[$key] === null || $args[$key] === '') {
            return $default;
        }
        $v = (int) $args[$key];
        if ($min !== null && $v < $min) {
            $v = $min;
        }
        if ($max !== null && $v > $max) {
            $v = $max;
        }
        return $v;
    }

    /** @return array{year: int, month: int} */
    private static function shiftMonth(int $year, int $month, int $delta): array
    {
        $ts = mktime(12, 0, 0, $month + $delta, 1, $year);
        return ['year' => (int) date('Y', $ts), 'month' => (int) date('n', $ts)];
    }
}
