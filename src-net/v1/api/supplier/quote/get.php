<?php
class GetterQuote
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    /**
     * 分页查询货物列表，支持关键字搜索（name, number, supplier_name, contact）
     * 支持 supplier_id 过滤（用于按供应商管理）
     */
    public function getList()
    {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $size = isset($_GET['size']) ? (int) $_GET['size'] : 10;
        $offset = ($page - 1) * $size;
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $supplierNumber = isset($_GET['supplier_number']) ? $_GET['supplier_number'] : '';

        $sql = "SELECT q.*, IFNULL(s.name, '该供应商已删除') as supplier_name
            FROM supplier_quote q
            LEFT JOIN supplier_main s ON q.supplier_id = s.id";
        $countSql = "SELECT COUNT(*) FROM supplier_quote q
                 LEFT JOIN supplier_main s ON q.supplier_id = s.id";
        $params = [];
        $conditions = [];

        if ($keyword !== '') {
            $conditions[] = "(q.name LIKE ? OR q.number LIKE ? OR s.name LIKE ? OR s.contact LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if ($supplierNumber !== '') {
            $conditions[] = "s.number = ?";
            $params[] = $supplierNumber;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= $where . " ORDER BY q.id DESC LIMIT $size OFFSET $offset";
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
