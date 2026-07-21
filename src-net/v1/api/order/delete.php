<?php
// order/delete.php
// 软删除订单（仅打删除标记）

class DeleterOrder
{
    private $db;

    public function __construct(DbSet $dbWriter)
    {
        $this->db = $dbWriter;
    }

    public function deleteOrder()
    {
        $orderNumber = isset($_GET['number']) ? $_GET['number'] : '';

        if (empty($orderNumber)) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单号不能为空'];
        }

        try {
            $affected = $this->db->update(
                'order_main',
                ['is_deleted' => 1],
                ['number' => $orderNumber]
            );

            if ($affected === 0) {
                return [
                    'success' => true,
                    'message' => '订单不存在或已删除',
                ];
            }

            return [
                'success' => true,
                'message' => '订单删除成功（软删除）',
            ];
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '删除失败：' . $e->getMessage()];
        }
    }
}
