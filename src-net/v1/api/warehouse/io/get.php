<?php
class GetterWarehouseIO
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

        $sql = "SELECT io.*, IFNULL(w.name, '该仓库已删除') as warehouse_name, q.name as product_name
            FROM warehouse_io io
            LEFT JOIN warehouse w ON io.warehouse_number = w.number
            LEFT JOIN supplier_quote q ON io.product_number = q.number";
        $countSql = "SELECT COUNT(*) FROM warehouse_io io
            LEFT JOIN warehouse w ON io.warehouse_number = w.number
            LEFT JOIN supplier_quote q ON io.product_number = q.number";
        $params = [];
        $where = '';

        if ($keyword !== '') {
            $where = " WHERE io.number LIKE ? OR w.name LIKE ? OR q.name LIKE ? OR io.product_number LIKE ? OR io.related_order_no LIKE ?";
            $kw = "%$keyword%";
            $params = [$kw, $kw, $kw, $kw, $kw];
        }

        $sql .= $where . " ORDER BY io.id DESC LIMIT $size OFFSET $offset";
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
