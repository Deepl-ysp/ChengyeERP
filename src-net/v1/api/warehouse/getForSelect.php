<?php
class GetterWarehouseForSelect
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

        $sql = "SELECT id, number, name, contact FROM warehouse WHERE 1=1";
        $params = [];
        if ($keyword !== '') {
            $sql .= " AND (name LIKE ? OR contact LIKE ?)";
            $params = ["%$keyword%", "%$keyword%"];
        }
        $sql .= " ORDER BY name LIMIT $limit";
        $list = $this->db->query($sql, $params);
        return ['list' => $list];
    }
}
