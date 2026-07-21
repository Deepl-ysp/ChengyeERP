<?php
// order/getDetail.php
// 订单详情查询

class GetterOrderDetail
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getDetail()
    {
        $orderNumber = isset($_GET['number']) ? $_GET['number'] : '';

        if (empty($orderNumber)) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单号不能为空'];
        }

        // 1. 查询订单基本信息
        $orderSql = "SELECT id, number, customer_name, customer_number, seller_name,
                            receiver_name, receiver_address, receiver_phone,
                            total_quantity, total_amount, paid_amount,
                            (total_amount - paid_amount) as unpaid_amount,
                            payment_status, order_status, remark, image_path,
                            is_deleted, create_time, update_time
                     FROM order_main
                     WHERE number = ? LIMIT 1";
        $orderResult = $this->db->query($orderSql, [$orderNumber]);

        if (empty($orderResult)) {
            http_response_code(404);
            return ['success' => false, 'message' => '订单不存在'];
        }

        $order = $orderResult[0];

        // 解析订单类型
        $order['order_type'] = $this->parseOrderType($order['remark'] ?? '');
        $order['remark'] = $this->cleanRemark($order['remark'] ?? '');

        // 2. 查询订单商品明细
        $itemsSql = "SELECT id, order_number, product_name, product_number, unit,
                            quantity, unit_price, subtotal, specification,
                            warehouse_number, remark, create_time
                     FROM order_item
                     WHERE order_number = ?
                     ORDER BY id ASC";
        $items = $this->db->query($itemsSql, [$orderNumber]);

        // 3. 查询每个订单项的扣减明细
        foreach ($items as &$item) {
            $deductionSql = "SELECT d.id, d.order_item_id, d.order_number,
                                    d.product_number, d.warehouse_number,
                                    d.deducted_quantity, d.unit_cost, d.create_time,
                                    q.name as product_name, q.specification, q.unit,
                                    w.name as warehouse_name
                             FROM order_item_deduction d
                             LEFT JOIN supplier_quote q ON d.product_number = q.number
                             LEFT JOIN warehouse w ON d.warehouse_number = w.number
                             WHERE d.order_item_id = ?
                             ORDER BY d.id ASC";
            $item['deductions'] = $this->db->query($deductionSql, [$item['id']]);
        }
        unset($item);

        // 4. 查询支付记录
        $paymentSql = "SELECT id, number, order_number, amount, payment_method,
                              payment_time, operator, remark, create_time
                       FROM order_payment
                       WHERE order_number = ?
                       ORDER BY payment_time DESC, id DESC";
        $payments = $this->db->query($paymentSql, [$orderNumber]);

        return [
            'success' => true,
            'order' => $order,
            'items' => $items,
            'payments' => $payments,
        ];
    }

    /**
     * 从 remark 中解析订单类型
     */
    private function parseOrderType(string $remark): string
    {
        if (preg_match('/\[供货\]/', $remark)) {
            return 'supply';
        }
        if (preg_match('/\[零售\]/', $remark)) {
            return 'retail';
        }
        return 'supply';
    }

    /**
     * 清理 remark 中的类型前缀
     */
    private function cleanRemark(string $remark): string
    {
        return trim(preg_replace('/\[(供货|零售)\]\s*/', '', $remark));
    }
}
