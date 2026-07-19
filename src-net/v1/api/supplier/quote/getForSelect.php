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

        $sql = "SELECT q.number, q.name, q.unit, q.specification, s.name as supplier_name
            FROM supplier_quote q
            LEFT JOIN supplier_main s ON q.supplier_id = s.id";
        $params = [];
        $where = '';

        if ($keyword !== '') {
            $where = " WHERE q.name LIKE ? OR q.number LIKE ? OR s.name LIKE ?";
            $params = ["%$keyword%", "%$keyword%", "%$keyword%"];
        }

        $sql .= $where . " ORDER BY q.id DESC LIMIT $limit";
        $list = $this->db->query($sql, $params);
        return ['list' => $list];
    }
}
