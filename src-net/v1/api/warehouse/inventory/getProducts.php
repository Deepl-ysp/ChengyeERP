<?php
class GetterWarehouseProducts
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    /**
     * 获取指定仓库可用的产品列表（用于出入库时搜索）
     */
    public function getProducts()
    {
        $warehouseNumber = isset($_GET['warehouse_number']) ? $_GET['warehouse_number'] : '';
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

        if ($warehouseNumber === '') {
            return ['list' => [], 'total' => 0];
        }

        $sql = "SELECT i.product_number, i.quantity, i.min_stock, i.max_stock,
                       q.name as product_name, q.unit, q.specification,
                       s.name as supplier_name
                FROM warehouse_inventory i
                LEFT JOIN supplier_quote q ON i.product_number = q.number
                LEFT JOIN supplier_main s ON q.supplier_id = s.id
                WHERE i.warehouse_number = ?";
        $params = [$warehouseNumber];

        if ($keyword !== '') {
            $sql .= " AND (q.name LIKE ? OR i.product_number LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw;
            $params[] = $kw;
        }

        $sql .= " ORDER BY i.id DESC LIMIT $limit";
        $list = $this->db->query($sql, $params);

        return [
            'list' => $list,
            'total' => count($list)
        ];
    }
}
