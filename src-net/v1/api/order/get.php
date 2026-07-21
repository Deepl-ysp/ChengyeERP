<?php
// order/get.php
// 订单列表查询

class GetterOrder
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList(...$args)
    {
        // 兼容 Mezon Router 的 ($route, $params) 调用方式
        $page = 1;
        $size = 10;
        if (count($args) >= 2) {
            $page = (int) $args[0];
            $size = (int) $args[1];
        } elseif (count($args) === 1 && is_array($args[0])) {
            $page = (int) ($args[0]['page'] ?? 1);
            $size = (int) ($args[0]['size'] ?? 10);
        }

        // 也支持 GET 参数
        $page = (int) ($_GET['page'] ?? $page);
        $size = (int) ($_GET['size'] ?? $size);
        $page = max(1, $page);
        $size = max(1, min(100, $size));
        $offset = ($page - 1) * $size;

        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
        $orderStatus = isset($_GET['order_status']) ? $_GET['order_status'] : '';
        $orderType = isset($_GET['order_type']) ? $_GET['order_type'] : '';

        // 基础 SQL（排除已删除订单）
        $where = ["is_deleted = 0"];
        $params = [];

        if ($keyword !== '') {
            $where[] = "(number LIKE ? OR customer_name LIKE ? OR receiver_name LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if ($paymentStatus !== '') {
            $where[] = "payment_status = ?";
            $params[] = (int) $paymentStatus;
        }
        if ($orderStatus !== '') {
            $where[] = "order_status = ?";
            $params[] = (int) $orderStatus;
        }
        if ($orderType !== '') {
            // order_type 存在 remark 字段中（[供货] / [零售]）
            $typeText = $orderType === 'supply' ? '供货' : '零售';
            $where[] = "remark LIKE ?";
            $params[] = "[%{$typeText}%]";
        }

        $whereSql = implode(' AND ', $where);

        // 查询列表
        $sql = "SELECT id, number, customer_name, customer_number, seller_name,
                       receiver_name, receiver_phone, total_quantity, total_amount,
                       paid_amount, (total_amount - paid_amount) as unpaid_amount,
                       payment_status, order_status, remark, image_path,
                       create_time, update_time
                FROM order_main
                WHERE {$whereSql}
                ORDER BY id DESC LIMIT {$size} OFFSET {$offset}";

        $list = $this->db->query($sql, $params);

        // 从 remark 中解析订单类型
        foreach ($list as &$row) {
            $row['order_type'] = $this->parseOrderType($row['remark'] ?? '');
            // 清理 remark 中的类型前缀
            $row['remark'] = $this->cleanRemark($row['remark'] ?? '');
        }
        unset($row);

        // 查询总数
        $countSql = "SELECT COUNT(*) FROM order_main WHERE {$whereSql}";
        $countResult = $this->db->query($countSql, $params);
        $total = (int) ($countResult[0]['COUNT(*)'] ?? 0);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size,
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
        return 'supply'; // 默认供货
    }

    /**
     * 清理 remark 中的类型前缀
     */
    private function cleanRemark(string $remark): string
    {
        return trim(preg_replace('/\[(供货|零售)\]\s*/', '', $remark));
    }
}
