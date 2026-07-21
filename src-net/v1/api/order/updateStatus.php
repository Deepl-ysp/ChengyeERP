<?php
// order/updateStatus.php
// 更新订单状态（使用 number 字段定位）

class UpdaterOrderStatus
{
    private $db;

    public function __construct(DbSet $dbWriter)
    {
        $this->db = $dbWriter;
    }

    public function updateStatus()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        $orderNumber = $data['number'] ?? '';
        $newStatus = (int) ($data['order_status'] ?? -1);

        if (empty($orderNumber)) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单号不能为空'];
        }

        // 校验状态值：0=待处理 1=已确认 2=已发货 3=已完成 4=已取消
        if ($newStatus < 0 || $newStatus > 4) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的订单状态'];
        }

        try {
            $affected = $this->db->update(
                'order_main',
                ['order_status' => $newStatus],
                ['number' => $orderNumber]
            );

            if ($affected === 0) {
                return [
                    'success' => true,
                    'message' => '订单状态未变化（订单不存在或状态相同）',
                ];
            }

            $statusText = ['待处理', '已确认', '已发货', '已完成', '已取消'];
            return [
                'success' => true,
                'message' => '订单状态已更新为：' . ($statusText[$newStatus] ?? '未知'),
            ];
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '更新失败：' . $e->getMessage()];
        }
    }
}
