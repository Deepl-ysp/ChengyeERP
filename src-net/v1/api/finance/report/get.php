<?php
// finance/report/get.php
// 利润分析报表

class GetterFinanceReport
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    /**
     * 利润分析：订单收入 - 采购成本 - 其他支出
     */
    public function getProfit($route, $params = [])
    {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $startTime = $startDate . ' 00:00:00';
        $endTime = $endDate . ' 23:59:59';

        // 订单收入（total_amount，未取消）
        $orderResult = $this->db->query(
            "SELECT
                COALESCE(SUM(total_amount), 0) AS order_total,
                COALESCE(SUM(paid_amount), 0) AS order_paid,
                COUNT(*) AS order_count
             FROM order_main
             WHERE is_deleted = 0 AND order_status != 4
             AND create_time BETWEEN ? AND ?",
            [$startTime, $endTime]
        );
        $orderTotal = (float) ($orderResult[0]['order_total'] ?? 0);
        $orderPaid = (float) ($orderResult[0]['order_paid'] ?? 0);
        $orderCount = (int) ($orderResult[0]['order_count'] ?? 0);

        // 供应商付款（flow_type=1）
        $supplierPaidResult = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS paid
             FROM finance_flow
             WHERE is_deleted = 0 AND flow_type = 1
             AND flow_time BETWEEN ? AND ?",
            [$startTime, $endTime]
        );
        $supplierPaid = (float) ($supplierPaidResult[0]['paid'] ?? 0);

        // 其他支出（flow_type=3）
        $otherExpenseResult = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS exp
             FROM finance_flow
             WHERE is_deleted = 0 AND flow_type = 3
             AND flow_time BETWEEN ? AND ?",
            [$startTime, $endTime]
        );
        $otherExpense = (float) ($otherExpenseResult[0]['exp'] ?? 0);

        // 其他收入（flow_type=2）
        $otherIncomeResult = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS inc
             FROM finance_flow
             WHERE is_deleted = 0 AND flow_type = 2
             AND flow_time BETWEEN ? AND ?",
            [$startTime, $endTime]
        );
        $otherIncome = (float) ($otherIncomeResult[0]['inc'] ?? 0);

        $totalIncome = $orderTotal + $otherIncome;
        $totalCost = $supplierPaid + $otherExpense;
        $grossProfit = $totalIncome - $totalCost;
        $profitMargin = $totalIncome > 0 ? round(($grossProfit / $totalIncome) * 100, 2) : 0;

        // 按月趋势（近12个月）
        $monthly = $this->db->query(
            "SELECT
                DATE_FORMAT(om.create_time, '%Y-%m') AS month,
                COALESCE(SUM(om.total_amount), 0) AS income
             FROM order_main om
             WHERE om.is_deleted = 0 AND om.order_status != 4
             AND om.create_time >= DATE_SUB(?, INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(om.create_time, '%Y-%m')
             ORDER BY month ASC",
            [$endDate]
        );

        $monthlyExpense = $this->db->query(
            "SELECT
                DATE_FORMAT(f.flow_time, '%Y-%m') AS month,
                COALESCE(SUM(f.amount), 0) AS expense
             FROM finance_flow f
             WHERE f.is_deleted = 0 AND f.direction = 1
             AND f.flow_time >= DATE_SUB(?, INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(f.flow_time, '%Y-%m')
             ORDER BY month ASC",
            [$endDate]
        );

        $expenseMap = [];
        foreach ($monthlyExpense as $m) {
            $expenseMap[$m['month']] = (float) $m['expense'];
        }
        $monthlyProfit = [];
        foreach ($monthly as $m) {
            $month = $m['month'];
            $inc = (float) $m['income'];
            $exp = $expenseMap[$month] ?? 0;
            $monthlyProfit[] = [
                'month' => $month,
                'income' => $inc,
                'expense' => $exp,
                'profit' => $inc - $exp,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'period' => [$startDate, $endDate],
                'order_total' => $orderTotal,
                'order_paid' => $orderPaid,
                'order_count' => $orderCount,
                'other_income' => $otherIncome,
                'total_income' => $totalIncome,
                'supplier_paid' => $supplierPaid,
                'other_expense' => $otherExpense,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'profit_margin' => $profitMargin,
                'monthly' => $monthlyProfit,
            ],
        ];
    }
}
