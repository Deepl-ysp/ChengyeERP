<?php
// finance/receivable/get.php
// 应收账款查询

class GetterFinanceReceivable
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
        $paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

        $where = ["om.is_deleted = 0", "om.order_status != 4", "(om.total_amount - om.paid_amount) > 0.01"];
        $params = [];

        if ($keyword !== '') {
            $where[] = "(om.customer_name LIKE ? OR om.number = ?)";
            $kw = "%$keyword%";
            $params[] = $kw;
            $params[] = is_numeric($keyword) ? (int) $keyword : 0;
        }
        if ($paymentStatus !== '') {
            $where[] = "om.payment_status = ?";
            $params[] = (int) $paymentStatus;
        }

        $whereSql = implode(' AND ', $where);

        $countResult = $this->db->query(
            "SELECT COUNT(*) AS total FROM order_main om WHERE $whereSql",
            $params
        );
        $total = (int) ($countResult[0]['total'] ?? 0);

        $sumResult = $this->db->query(
            "SELECT
                COALESCE(SUM(om.total_amount), 0) AS total_amount,
                COALESCE(SUM(om.paid_amount), 0) AS paid_amount
             FROM order_main om WHERE $whereSql",
            $params
        );
        $sumAmount = (float) ($sumResult[0]['total_amount'] ?? 0);
        $sumPaid = (float) ($sumResult[0]['paid_amount'] ?? 0);

        $list = $this->db->query(
            "SELECT om.id, om.number, om.customer_number, om.customer_name, om.receiver_name,
                om.total_amount, om.paid_amount, (om.total_amount - om.paid_amount) AS unreceived_amount,
                om.payment_status, om.order_status, om.create_time,
                (SELECT MAX(payment_time) FROM order_payment WHERE order_number = om.number) AS last_payment_time
             FROM order_main om
             WHERE $whereSql
             ORDER BY (om.total_amount - om.paid_amount) DESC, om.create_time DESC
             LIMIT $offset, $size",
            $params
        );

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size,
            'sum_total' => $sumAmount,
            'sum_paid' => $sumPaid,
            'sum_unreceived' => $sumAmount - $sumPaid,
        ];
    }

    /**
     * 按客户汇总应收
     */
    public function getByCustomer($route, $params = [])
    {
        $list = $this->db->query(
            "SELECT om.customer_number, om.customer_name,
                COUNT(*) AS order_count,
                COALESCE(SUM(om.total_amount), 0) AS total_amount,
                COALESCE(SUM(om.paid_amount), 0) AS paid_amount,
                COALESCE(SUM(om.total_amount - om.paid_amount), 0) AS unreceived_amount
             FROM order_main om
             WHERE om.is_deleted = 0 AND om.order_status != 4
             GROUP BY om.customer_number, om.customer_name
             HAVING COALESCE(SUM(om.total_amount - om.paid_amount), 0) > 0.01
             ORDER BY COALESCE(SUM(om.total_amount - om.paid_amount), 0) DESC
             LIMIT 100"
        );
        return ['list' => $list];
    }

    /**
     * 客户对账：按客户+时间段查询订单和付款记录，计算欠款
     */
    public function getReconciliation($route, $params = [])
    {
        $customerNumber = isset($_GET['customer_number']) ? (int) $_GET['customer_number'] : 0;
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

        if ($customerNumber <= 0) {
            return ['success' => false, 'message' => '客户编号不能为空'];
        }

        $where = ["om.is_deleted = 0", "om.order_status != 4", "om.customer_number = ?"];
        $params = [$customerNumber];

        if ($startDate !== '') {
            $where[] = "om.create_time >= ?";
            $params[] = $startDate . ' 00:00:00';
        }
        if ($endDate !== '') {
            $where[] = "om.create_time <= ?";
            $params[] = $endDate . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $orders = $this->db->query(
            "SELECT om.id, om.number, om.customer_name, om.total_amount, om.paid_amount,
                (om.total_amount - om.paid_amount) AS unreceived_amount,
                om.order_status, om.payment_status, om.create_time
             FROM order_main om
             WHERE $whereSql
             ORDER BY om.create_time DESC",
            $params
        );

        $payments = [];
        if (!empty($orders)) {
            $orderNumbers = array_column($orders, 'number');
            $placeholders = implode(',', array_fill(0, count($orderNumbers), '?'));
            $payments = $this->db->query(
                "SELECT op.order_number, op.amount, op.payment_method, op.payment_time, op.operator
                 FROM order_payment op
                 WHERE op.order_number IN ($placeholders)
                 ORDER BY op.payment_time DESC",
                $orderNumbers
            );
        }

        $orderTotal = 0;
        $paidTotal = 0;
        foreach ($orders as $order) {
            $orderTotal += (float) $order['total_amount'];
            $paidTotal += (float) $order['paid_amount'];
        }

        $customerInfo = [];
        if (!empty($orders)) {
            $customerInfo = $this->db->query(
                "SELECT name, phone, address FROM customer_main WHERE number = ? LIMIT 1",
                [$customerNumber]
            );
        }

        return [
            'success' => true,
            'data' => [
                'customer_number' => $customerNumber,
                'customer_name' => $customerInfo[0]['name'] ?? '',
                'customer_phone' => $customerInfo[0]['phone'] ?? '',
                'customer_address' => $customerInfo[0]['address'] ?? '',
                'period' => ['start' => $startDate, 'end' => $endDate],
                'summary' => [
                    'order_count' => count($orders),
                    'order_total' => $orderTotal,
                    'paid_total' => $paidTotal,
                    'unpaid_total' => $orderTotal - $paidTotal,
                ],
                'orders' => $orders,
                'payments' => $payments,
            ],
        ];
    }
}
