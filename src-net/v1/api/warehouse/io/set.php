<?php
class SetterWarehouseIO
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbReader, DbGet $reader)
    {
        $this->db = $dbReader;
        $this->reader = $reader;
    }

    public function setIO(...$args)
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['error' => '无效的JSON数据'];
        }

        $warehouseNumber = (int) ($data['warehouse_number'] ?? 0);
        $productNumber = (int) ($data['product_number'] ?? 0);
        $direction = (int) ($data['direction'] ?? 1);
        $quantity = (int) ($data['quantity'] ?? 0);

        if (!$warehouseNumber || !$productNumber || $quantity <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '仓库、产品和数量不能为空'];
        }

        // 查当前库存
        $inv = $this->reader->query(
            "SELECT id, quantity FROM warehouse_inventory WHERE warehouse_number = ? AND product_number = ? LIMIT 1",
            [$warehouseNumber, $productNumber]
        );

        $beforeQuantity = (int) ($inv[0]['quantity'] ?? 0);
        if ($direction === 1) {
            $afterQuantity = $beforeQuantity + $quantity;
        } else {
            $afterQuantity = $beforeQuantity - $quantity;
            if ($afterQuantity < 0) {
                http_response_code(400);
                return ['success' => false, 'message' => '出库数量超过当前库存'];
            }
        }

        // 写入进出库记录
        $insertData = [
            'warehouse_number' => $warehouseNumber,
            'product_number' => $productNumber,
            'direction' => $direction,
            'quantity' => $quantity,
            'before_quantity' => $beforeQuantity,
            'after_quantity' => $afterQuantity,
            'related_order_no' => $data['related_order_no'] ?? '',
            'operator' => $data['operator'] ?? '系统',
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $this->db->insert('warehouse_io', $insertData);
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '写入进出库记录失败：' . $e->getMessage()];
        }

        // 更新或创建库存
        try {
            if (!empty($inv[0]['id'])) {
                $this->db->update(
                    'warehouse_inventory',
                    ['quantity' => $afterQuantity],
                    ['id' => $inv[0]['id']]
                );
            } else {
                $this->db->insert('warehouse_inventory', [
                    'warehouse_number' => $warehouseNumber,
                    'product_number' => $productNumber,
                    'quantity' => $afterQuantity,
                    'min_stock' => 0,
                    'max_stock' => 0,
                    'remark' => '由进出库自动创建',
                ]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '更新库存失败：' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => $direction === 1 ? '入库成功' : '出库成功',
            'data' => [
                'before_quantity' => $beforeQuantity,
                'after_quantity' => $afterQuantity,
            ]
        ];
    }
}
