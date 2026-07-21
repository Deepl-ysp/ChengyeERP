<?php
// order/payment/set.php
// 新增订单支付记录（同时更新订单主表的已付金额和支付状态，同步财务流水）

class SetterOrderPayment
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbWriter, DbGet $dbReader)
    {
        $this->db = $dbWriter;
        $this->reader = $dbReader;
    }

    public function setPayment()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        $orderNumber = $data['order_number'] ?? '';
        $amount = (float) ($data['amount'] ?? 0);
        $paymentMethod = (int) ($data['payment_method'] ?? 0);
        $operator = $data['operator'] ?? '系统';
        $remark = $data['remark'] ?? '';
        $isFinance = (int) ($data['is_finance'] ?? 1);

        if (empty($orderNumber)) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单号不能为空'];
        }

        if ($amount <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '支付金额必须大于0'];
        }

        $orderResult = $this->reader->query(
            "SELECT id, number, total_amount, paid_amount, order_status, is_deleted, customer_name
             FROM order_main WHERE number = ? LIMIT 1",
            [$orderNumber]
        );

        if (empty($orderResult)) {
            http_response_code(404);
            return ['success' => false, 'message' => '订单不存在'];
        }

        $order = $orderResult[0];

        if ((int) $order['is_deleted'] === 1) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单已删除，无法支付'];
        }

        $totalAmount = (float) $order['total_amount'];
        $currentPaid = (float) $order['paid_amount'];
        $newPaidAmount = $currentPaid + $amount;

        if ($newPaidAmount > $totalAmount) {
            http_response_code(400);
            return [
                'success' => false,
                'message' => "支付金额超出订单总额（订单总额：{$totalAmount}，已付：{$currentPaid}，本次支付：{$amount}）",
            ];
        }

        $paymentNumber = $this->generatePaymentNumber();

        try {
            $this->db->insert('order_payment', [
                'number' => $paymentNumber,
                'order_number' => $orderNumber,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_time' => date('Y-m-d H:i:s'),
                'operator' => $operator,
                'remark' => $remark,
            ]);
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '支付记录写入失败：' . $e->getMessage()];
        }

        $newPaymentStatus = 1;
        if ($newPaidAmount >= $totalAmount) {
            $newPaymentStatus = 2;
        } elseif ($newPaidAmount <= 0) {
            $newPaymentStatus = 0;
        }

        try {
            $this->db->update(
                'order_main',
                [
                    'paid_amount' => $newPaidAmount,
                    'payment_status' => $newPaymentStatus,
                ],
                ['number' => $orderNumber]
            );
        } catch (\PDOException $e) {
            $this->db->delete('order_payment', ['number' => $paymentNumber]);
            http_response_code(500);
            return ['success' => false, 'message' => '订单支付状态更新失败：' . $e->getMessage()];
        }

        if ($isFinance) {
            $this->syncFinanceFlow($orderNumber, $order['customer_name'], $amount, $paymentMethod, $operator, $remark);
        }

        $statusText = ['未支付', '部分支付', '全额支付'];

        return [
            'success' => true,
            'message' => '支付记录添加成功',
            'data' => [
                'payment_number' => $paymentNumber,
                'paid_amount' => $newPaidAmount,
                'unpaid_amount' => $totalAmount - $newPaidAmount,
                'payment_status' => $newPaymentStatus,
                'payment_status_text' => $statusText[$newPaymentStatus],
            ],
        ];
    }

    private function syncFinanceFlow($orderNumber, $customerName, $amount, $paymentMethod, $operator, $remark)
    {
        try {
            $this->db->insert('finance_flow', [
                'flow_type' => 0,
                'direction' => 0,
                'party_type' => 0,
                'party_number' => (int) $orderNumber,
                'party_name' => $customerName ?: '客户',
                'related_number' => (int) $orderNumber,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'operator' => $operator,
                'remark' => $remark ?: '订单收款',
            ]);
        } catch (\PDOException $e) {
            // 静默失败
        }
    }

    private function generatePaymentNumber(): int
    {
        return (int) (round(microtime(true) * 1000) * 10000 + mt_rand(0, 9999));
    }
}
