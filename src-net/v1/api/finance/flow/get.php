<?php
// finance/flow/get.php
// 资金流水列表查询

class GetterFinanceFlow
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

        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $flowType = isset($_GET['flow_type']) ? $_GET['flow_type'] : '';
        $direction = isset($_GET['direction']) ? $_GET['direction'] : '';
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $partyNumber = isset($_GET['party_number']) ? $_GET['party_number'] : '';

        $where = ["f.is_deleted = 0"];
        $params = [];

        if ($keyword !== '') {
            $where[] = "(f.party_name LIKE ? OR f.remark LIKE ? OR f.number = ?)";
            $kw = "%$keyword%";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = is_numeric($keyword) ? (int) $keyword : 0;
        }
        if ($flowType !== '') {
            $where[] = "f.flow_type = ?";
            $params[] = (int) $flowType;
        }
        if ($direction !== '') {
            $where[] = "f.direction = ?";
            $params[] = (int) $direction;
        }
        if ($partyNumber !== '') {
            $where[] = "f.party_number = ?";
            $params[] = (int) $partyNumber;
        }
        if ($startDate !== '') {
            $where[] = "f.flow_time >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate !== '') {
            $where[] = "f.flow_time <= ?";
            $params[] = $endDate . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        // 统计
        $countResult = $this->db->query(
            "SELECT COUNT(*) AS total FROM finance_flow f WHERE $whereSql",
            $params
        );
        $total = (int) ($countResult[0]['total'] ?? 0);

        // 合计
        $sumResult = $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN f.direction = 0 THEN f.amount ELSE 0 END), 0) AS income_total,
                COALESCE(SUM(CASE WHEN f.direction = 1 THEN f.amount ELSE 0 END), 0) AS expense_total
             FROM finance_flow f WHERE $whereSql",
            $params
        );

        // 列表
        $list = $this->db->query(
            "SELECT f.* FROM finance_flow f WHERE $whereSql
             ORDER BY f.flow_time DESC, f.id DESC
             LIMIT $offset, $size",
            $params
        );

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size,
            'income_total' => (float) ($sumResult[0]['income_total'] ?? 0),
            'expense_total' => (float) ($sumResult[0]['expense_total'] ?? 0),
        ];
    }

    /**
     * 资金概览数据
     */
    public function getOverview()
    {
        // 累计收入/支出
        $sumResult = $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN direction = 0 THEN amount ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END), 0) AS total_expense,
                COUNT(*) AS flow_count
             FROM finance_flow WHERE is_deleted = 0"
        );
        $totalIncome = (float) ($sumResult[0]['total_income'] ?? 0);
        $totalExpense = (float) ($sumResult[0]['total_expense'] ?? 0);

        // 订单应收（order_main 中 paid_amount 与 total_amount 差额）
        $orderResult = $this->db->query(
            "SELECT
                COALESCE(SUM(total_amount), 0) AS order_total,
                COALESCE(SUM(paid_amount), 0) AS order_paid
             FROM order_main WHERE is_deleted = 0 AND order_status != 4"
        );
        $orderTotal = (float) ($orderResult[0]['order_total'] ?? 0);
        $orderPaid = (float) ($orderResult[0]['order_paid'] ?? 0);
        $orderUnreceived = $orderTotal - $orderPaid;

        // 近30天收支趋势（按日）
        $startDate = date('Y-m-d', strtotime('-29 days'));
        $trend = $this->db->query(
            "SELECT
                DATE(flow_time) AS date,
                COALESCE(SUM(CASE WHEN direction = 0 THEN amount ELSE 0 END), 0) AS income,
                COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END), 0) AS expense
             FROM finance_flow
             WHERE is_deleted = 0 AND flow_time >= ?
             GROUP BY DATE(flow_time)
             ORDER BY date ASC",
            [$startDate . ' 00:00:00']
        );

        // 按客户汇总应收
        $byCustomer = $this->db->query(
            "SELECT customer_number, customer_name,
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COALESCE(SUM(paid_amount), 0) AS paid_amount
             FROM order_main
             WHERE is_deleted = 0 AND order_status != 4
             GROUP BY customer_number, customer_name
             HAVING total_amount - paid_amount > 0.01
             ORDER BY (total_amount - paid_amount) DESC
             LIMIT 10"
        );

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_amount' => $totalIncome - $totalExpense,
            'flow_count' => (int) ($sumResult[0]['flow_count'] ?? 0),
            'order_total' => $orderTotal,
            'order_paid' => $orderPaid,
            'order_unreceived' => $orderUnreceived,
            'trend' => $trend,
            'top_receivable' => $byCustomer,
        ];
    }
}
