<?php
class GetterCustomerForSelect
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
        // 可选：仅返回指定状态的客户（如 status=0 仅启用）
        // 不传则返回所有状态
        $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

        $sql = "SELECT id, number, name, contact, phone_number, address, status FROM customer WHERE 1=1";
        $params = [];
        if ($keyword !== '') {
            $sql .= " AND (name LIKE ? OR contact LIKE ? OR phone_number LIKE ?)";
            $kw = "%$keyword%";
            $params = [$kw, $kw, $kw];
        }
        if ($statusFilter !== null) {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY name LIMIT $limit";
        $list = $this->db->query($sql, $params);
        return ['list' => $list];
    }
}
