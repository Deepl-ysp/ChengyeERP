<?php
// warehouse/purchase/get.php
// 采购单查询

class GetterWarehousePurchase
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList()
    {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $size = isset($_GET['size']) ? (int) $_GET['size'] : 10;
        $offset = ($page - 1) * $size;
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';

        $sql = "SELECT po.* FROM purchase_order po WHERE po.is_deleted = 0";
        $countSql = "SELECT COUNT(*) FROM purchase_order po WHERE po.is_deleted = 0";
        $params = [];
        $conditions = [];

        if ($keyword !== '') {
            $conditions[] = "(po.number LIKE ? OR po.supplier_name LIKE ? OR po.warehouse_name LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if ($status !== '') {
            $conditions[] = "po.status = ?";
            $params[] = (int) $status;
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(' AND ', $conditions);
            $countSql .= " AND " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY po.create_time DESC LIMIT $size OFFSET $offset";
        $list = $this->db->query($sql, $params);

        $countResult = $this->db->query($countSql, $params);
        $total = (int) ($countResult[0]['COUNT(*)'] ?? 0);

        foreach ($list as &$item) {
            $item['total_amount'] = (float) $item['total_amount'];
        }

        return ['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size];
    }

    public function getDetail()
    {
        $purchaseNumber = isset($_GET['purchase_number']) ? (int) $_GET['purchase_number'] : 0;
        if ($purchaseNumber <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '采购单号不能为空'];
        }

        $po = $this->db->query(
            "SELECT * FROM purchase_order WHERE number = ? AND is_deleted = 0 LIMIT 1",
            [$purchaseNumber]
        );
        if (empty($po)) {
            http_response_code(404);
            return ['success' => false, 'message' => '采购单不存在'];
        }

        $items = $this->db->query(
            "SELECT * FROM purchase_item WHERE purchase_number = ? ORDER BY id ASC",
            [$purchaseNumber]
        );

        foreach ($items as &$item) {
            $item['unit_price'] = (float) $item['unit_price'];
            $item['total_amount'] = (float) $item['total_amount'];
        }

        return [
            'success' => true,
            'data' => [
                'order' => $po[0],
                'items' => $items,
            ],
        ];
    }
}
