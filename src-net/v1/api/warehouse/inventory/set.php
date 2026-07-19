<?php
class SetterWarehouseInventory
{
    private $db;

    public function __construct(DbSet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function setInventory(...$args)
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['error' => '无效的JSON数据'];
        }

        if (isset($data['id']) && $data['id'] > 0) {
            return $this->update($data);
        } else {
            return $this->insert($data);
        }
    }

    private function insert($data)
    {
        $insertData = [
            'warehouse_number' => (int) ($data['warehouse_number'] ?? 0),
            'product_number' => (int) ($data['product_number'] ?? 0),
            'quantity' => (int) ($data['quantity'] ?? 0),
            'min_stock' => (int) ($data['min_stock'] ?? 0),
            'max_stock' => (int) ($data['max_stock'] ?? 0),
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $id = $this->db->insert('warehouse_inventory', $insertData);
            return [
                'success' => true,
                'message' => '库存记录创建成功',
                'data' => ['id' => $id]
            ];
        } catch (\PDOException $e) {
            http_response_code(500);
            return [
                'success' => false,
                'message' => '数据库插入失败：' . $e->getMessage()
            ];
        }
    }

    private function update($data)
    {
        $id = (int) $data['id'];
        $updateData = [
            'warehouse_number' => (int) ($data['warehouse_number'] ?? 0),
            'product_number' => (int) ($data['product_number'] ?? 0),
            'quantity' => (int) ($data['quantity'] ?? 0),
            'min_stock' => (int) ($data['min_stock'] ?? 0),
            'max_stock' => (int) ($data['max_stock'] ?? 0),
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $affected = $this->db->update('warehouse_inventory', $updateData, ['id' => $id]);
            if ($affected === 0) {
                return [
                    'success' => true,
                    'message' => '更新成功（无变化）',
                    'data' => ['id' => $id]
                ];
            }
            return [
                'success' => true,
                'message' => '库存记录更新成功',
                'data' => ['id' => $id]
            ];
        } catch (\PDOException $e) {
            http_response_code(500);
            return [
                'success' => false,
                'message' => '数据库更新失败：' . $e->getMessage()
            ];
        }
    }
}
