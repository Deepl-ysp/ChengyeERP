<?php
// finance/flow/set.php
// 新增资金流水（客户收款或供应商付款）

class SetterFinanceFlow
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbWriter, DbGet $dbReader)
    {
        $this->db = $dbWriter;
        $this->reader = $dbReader;
    }

    public function setFlow()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        $flowType = (int) ($data['flow_type'] ?? 0);
        $partyType = (int) ($data['party_type'] ?? 0);
        $partyNumber = (int) ($data['party_number'] ?? 0);
        $partyName = $data['party_name'] ?? '';
        $relatedNumber = (int) ($data['related_number'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $paymentMethod = (int) ($data['payment_method'] ?? 0);
        $flowTime = $data['flow_time'] ?? date('Y-m-d H:i:s');
        $operator = $data['operator'] ?? '系统';
        $remark = $data['remark'] ?? '';

        // 收支方向
        $direction = ($flowType === 0 || $flowType === 2) ? 0 : 1;

        if ($amount <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '金额必须大于0'];
        }

        // 关联订单时验证
        if ($relatedNumber > 0) {
            $orderResult = $this->reader->query(
                "SELECT number, total_amount, paid_amount, is_deleted FROM order_main WHERE number = ? LIMIT 1",
                [$relatedNumber]
            );
            if (empty($orderResult)) {
                http_response_code(404);
                return ['success' => false, 'message' => '关联订单不存在'];
            }
            $order = $orderResult[0];
            if ((int) $order['is_deleted'] === 1) {
                http_response_code(400);
                return ['success' => false, 'message' => '关联订单已删除'];
            }
            if ($flowType === 0) {
                $unpaid = (float) $order['total_amount'] - (float) $order['paid_amount'];
                if ($amount > $unpaid + 0.01) {
                    http_response_code(400);
                    return [
                        'success' => false,
                        'message' => "收款金额超出未收金额（未收：{$unpaid}）",
                    ];
                }
            }
        }

        // 写入流水
        try {
            $this->db->insert('finance_flow', [
                'flow_type' => $flowType,
                'direction' => $direction,
                'party_type' => $partyType,
                'party_number' => $partyNumber,
                'party_name' => $partyName,
                'related_number' => $relatedNumber,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'flow_time' => $flowTime,
                'operator' => $operator,
                'remark' => $remark,
            ]);
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '流水写入失败：' . $e->getMessage()];
        }

        // 同步更新订单已付金额
        if ($relatedNumber > 0 && $flowType === 0) {
            $this->syncOrderPaid($relatedNumber);
        }

        return ['success' => true, 'message' => '资金流水记录成功'];
    }

    private function syncOrderPaid(int $orderNumber)
    {
        // 重新计算订单已收金额（避免与 order_payment 表不一致）
        $paidResult = $this->reader->query(
            "SELECT COALESCE(SUM(amount), 0) AS paid FROM order_payment WHERE order_number = ?",
            [$orderNumber]
        );
        $orderPaid = (float) ($paidResult[0]['paid'] ?? 0);

        $orderResult = $this->reader->query(
            "SELECT total_amount FROM order_main WHERE number = ? LIMIT 1",
            [$orderNumber]
        );
        if (empty($orderResult)) {
            return;
        }
        $total = (float) $orderResult[0]['total_amount'];
        $paymentStatus = 1;
        if ($orderPaid >= $total) {
            $paymentStatus = 2;
        } elseif ($orderPaid <= 0) {
            $paymentStatus = 0;
        }
        try {
            $this->db->update('order_main', [
                'paid_amount' => $orderPaid,
                'payment_status' => $paymentStatus,
            ], ['number' => $orderNumber]);
        } catch (\PDOException $e) {
            // 静默失败，不影响流水写入
        }
    }
}
