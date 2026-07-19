<?php
class GetterSupplierForSelect
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    /**
     * 根据关键字搜索供应商（返回 id, name, contact）
     */
    public function getForSelect()
    {
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        $sql = "SELECT id, number, name, contact FROM supplier_main WHERE 1=1";
        $params = [];
        if ($keyword !== '') {
            $sql .= " AND (name LIKE ? OR contact LIKE ?)";
            $params = ["%$keyword%", "%$keyword%"];
        }
        $sql .= " ORDER BY name LIMIT $limit";  // 直接拼接整数
        $list = $this->db->query($sql, $params);
        return ['list' => $list];
    }
}