<?php
class GetterQuoteForSelect
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getForSelect()
    {
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $supplierNumber = isset($_GET['supplier_number']) ? (int)$_GET['supplier_number'] : 0;
        $warehouseNumber = isset($_GET['warehouse_number']) ? (int)$_GET['warehouse_number'] : 0;

        $sql = "SELECT q.number, q.name, q.unit, q.specification, q.unit_price, q.supplier_id, s.name as supplier_name
            FROM supplier_quote q
            LEFT JOIN supplier_main s ON q.supplier_id = s.id";
        $params = [];
        $conditions = [];

        if ($warehouseNumber > 0) {
            $sql .= " INNER JOIN warehouse_inventory i ON q.number = i.product_number AND i.warehouse_number = ?";
            $params[] = $warehouseNumber;
        }

        if ($keyword !== '') {
            $conditions[] = "(q.name LIKE ? OR q.number LIKE ? OR s.name LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        if ($supplierNumber > 0) {
            $conditions[] = "s.number = ?";
            $params[] = $supplierNumber;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= $where . " ORDER BY q.id DESC LIMIT $limit";
        $list = $this->db->query($sql, $params);
        return ['list' => $list];
    }
}