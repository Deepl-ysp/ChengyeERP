<?php
class GetterCustomerPrice
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
        $customerNumber = isset($_GET['customer_number']) ? $_GET['customer_number'] : '';

        $sql = "SELECT p.*, IFNULL(c.name, '该客户已删除') as customer_name
            FROM customer_price p
            LEFT JOIN customer c ON p.customer_number = c.number";
        $countSql = "SELECT COUNT(*) FROM customer_price p
                 LEFT JOIN customer c ON p.customer_number = c.number";
        $params = [];
        $conditions = [];

        if ($keyword !== '') {
            $conditions[] = "(p.product_name LIKE ? OR p.number LIKE ? OR c.name LIKE ? OR c.contact LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw; $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        if ($customerNumber !== '') {
            $conditions[] = "p.customer_number = ?";
            $params[] = $customerNumber;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= $where . " ORDER BY p.id DESC LIMIT $size OFFSET $offset";
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
