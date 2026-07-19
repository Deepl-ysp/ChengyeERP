<?php
class DeleteWarehouseInventory
{
    private $db;

    public function __construct(DbSet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function deleteInventory()
    {
        $number = isset($_GET['number']) ? $_GET['number'] : null;
        if ($number === null || $number === '') {
            http_response_code(400);
            return ['error' => '无效的编号'];
        }

        try {
            $affected = $this->db->delete('warehouse_inventory', ['number' => $number]);
            if ($affected === 0) {
                return [
                    'success' => false,
                    'message' => '记录不存在或已删除'
                ];
            }
            return [
                'success' => true,
                'message' => '删除成功'
            ];
        } catch (\PDOException $e) {
            http_response_code(500);
            return [
                'success' => false,
                'message' => '数据库删除失败：' . $e->getMessage()
            ];
        }
    }
}
