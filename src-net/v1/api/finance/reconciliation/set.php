<?php
// finance/reconciliation/set.php
// 执行周期对账（按日/周/月/自定义时间段）

class SetterFinanceReconciliation
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbWriter, DbGet $dbReader)
    {
        $this->db = $dbWriter;
        $this->reader = $dbReader;
    }

    public function reconcile()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        $periodType = (int) ($data['period_type'] ?? 0);
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $reconciler = $data['reconciler'] ?? '系统';
        $remark = $data['remark'] ?? '';

        if (!$startDate || !$endDate) {
            http_response_code(400);
            return ['success' => false, 'message' => '时间范围不能为空'];
        }

        $startTime = $startDate . ' 00:00:00';
        $endTime = $endDate . ' 23:59:59';

        // 统计收支
        $sumResult = $this->reader->query(
            "SELECT
                COALESCE(SUM(CASE WHEN direction = 0 THEN amount ELSE 0 END), 0) AS income_total,
                COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END), 0) AS expense_total,
                COUNT(*) AS flow_count
             FROM finance_flow WHERE is_deleted = 0 AND flow_time BETWEEN ? AND ?",
            [$startTime, $endTime]
        );
        $income = (float) ($sumResult[0]['income_total'] ?? 0);
        $expense = (float) ($sumResult[0]['expense_total'] ?? 0);
        $flowCount = (int) ($sumResult[0]['flow_count'] ?? 0);

        // 订单数
        $orderResult = $this->reader->query(
            "SELECT COUNT(*) AS cnt FROM order_main
             WHERE is_deleted = 0 AND create_time BETWEEN ? AND ?",
            [$startTime, $endTime]
        );
        $orderCount = (int) ($orderResult[0]['cnt'] ?? 0);

        $periodLabel = $this->buildPeriodLabel($periodType, $startDate, $endDate);
        $isBalanced = 1; // 0=未对账 1=已对账 2=异常，本接口默认正常

        try {
            $this->db->insert('finance_reconciliation', [
                'period_type' => $periodType,
                'period_label' => $periodLabel,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'income_total' => $income,
                'expense_total' => $expense,
                'net_amount' => $income - $expense,
                'order_count' => $orderCount,
                'flow_count' => $flowCount,
                'is_balanced' => $isBalanced,
                'reconciler' => $reconciler,
                'reconcile_time' => date('Y-m-d H:i:s'),
                'remark' => $remark,
            ]);
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '对账保存失败：' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => "对账完成：收入 {$income}，支出 {$expense}，净收入 " . ($income - $expense),
            'data' => [
                'income_total' => $income,
                'expense_total' => $expense,
                'net_amount' => $income - $expense,
                'order_count' => $orderCount,
                'flow_count' => $flowCount,
            ],
        ];
    }

    private function buildPeriodLabel(int $type, string $start, string $end): string
    {
        switch ($type) {
            case 0:
                return $start; // 日
            case 1:
                $ts = strtotime($start);
                return date('Y-\WW', $ts); // 周
            case 2:
                return substr($start, 0, 7); // 月
            case 3:
            default:
                return $start . ' ~ ' . $end;
        }
    }
}
