<?php
// finance/reconciliation/get.php
// 对账记录查询

class GetterFinanceReconciliation
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList(...$args)
    {
        $page = 1;
        $size = 10;
        if (count($args) >= 2) {
            $page = (int) $args[0];
            $size = (int) $args[1];
        } elseif (count($args) === 1 && is_array($args[0])) {
            $page = (int) ($args[0]['page'] ?? 1);
            $size = (int) ($args[0]['size'] ?? 10);
        }

        $page = (int) ($_GET['page'] ?? $page);
        $size = (int) ($_GET['size'] ?? $size);
        $page = max(1, $page);
        $size = max(1, min(100, $size));
        $offset = ($page - 1) * $size;

        $periodType = isset($_GET['period_type']) ? $_GET['period_type'] : '';

        $where = ["1=1"];
        $params = [];
        if ($periodType !== '') {
            $where[] = "period_type = ?";
            $params[] = (int) $periodType;
        }
        $whereSql = implode(' AND ', $where);

        $countResult = $this->db->query(
            "SELECT COUNT(*) AS total FROM finance_reconciliation WHERE $whereSql",
            $params
        );
        $total = (int) ($countResult[0]['total'] ?? 0);

        $list = $this->db->query(
            "SELECT * FROM finance_reconciliation WHERE $whereSql
             ORDER BY start_time DESC LIMIT $offset, $size",
            $params
        );

        return ['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size];
    }

    /**
     * 周期对账详情：返回指定时间段内的所有订单、流水
     */
    public function getDetail()
    {
        $startTime = $_GET['start_time'] ?? '';
        $endTime = $_GET['end_time'] ?? '';

        if (!$startTime || !$endTime) {
            http_response_code(400);
            return ['success' => false, 'message' => '时间范围不能为空'];
        }

        // 订单
        $orders = $this->db->query(
            "SELECT id, number, customer_name, total_amount, paid_amount, payment_status, order_status, create_time
             FROM order_main
             WHERE is_deleted = 0 AND create_time BETWEEN ? AND ?
             ORDER BY create_time ASC",
            [$startTime, $endTime]
        );

        // 流水
        $flows = $this->db->query(
            "SELECT * FROM finance_flow
             WHERE is_deleted = 0 AND flow_time BETWEEN ? AND ?
             ORDER BY flow_time ASC",
            [$startTime, $endTime]
        );

        $incomeTotal = 0;
        $expenseTotal = 0;
        $orderCount = count($orders);
        foreach ($flows as $f) {
            if ((int) $f['direction'] === 0) {
                $incomeTotal += (float) $f['amount'];
            } else {
                $expenseTotal += (float) $f['amount'];
            }
        }

        return [
            'success' => true,
            'data' => [
                'orders' => $orders,
                'flows' => $flows,
                'order_count' => $orderCount,
                'flow_count' => count($flows),
                'income_total' => $incomeTotal,
                'expense_total' => $expenseTotal,
                'net_amount' => $incomeTotal - $expenseTotal,
            ],
        ];
    }
}
