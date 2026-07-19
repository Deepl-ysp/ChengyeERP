<?php
class GetterWarehouseInventory
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
        $warehouseNumber = isset($_GET['warehouse_number']) ? $_GET['warehouse_number'] : '';

        $sql = "SELECT i.*, IFNULL(w.name, '该仓库已删除') as warehouse_name, q.name as product_name, q.unit as product_unit, q.specification as product_spec
            FROM warehouse_inventory i
            LEFT JOIN warehouse w ON i.warehouse_number = w.number
            LEFT JOIN supplier_quote q ON i.product_number = q.number";
        $countSql = "SELECT COUNT(*) FROM warehouse_inventory i
            LEFT JOIN warehouse w ON i.warehouse_number = w.number
            LEFT JOIN supplier_quote q ON i.product_number = q.number";
        $params = [];
        $where = '';

        $conditions = [];
        if ($keyword !== '') {
            $conditions[] = "(i.number LIKE ? OR w.name LIKE ? OR q.name LIKE ? OR i.product_number LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if ($warehouseNumber !== '') {
            $conditions[] = "i.warehouse_number = ?";
            $params[] = $warehouseNumber;
        }
        if (!empty($conditions)) {
            $where = " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= $where . " ORDER BY i.id DESC LIMIT $size OFFSET $offset";
        $list = $this->db->query($sql, $params);

        $countSql .= $where;
        $countResult = $this->db->query($countSql, $params);
        $total = (int) ($countResult[0]['COUNT(*)'] ?? 0);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ];
    }
}
