<?php
class SetterWarehouseIO
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbWriter, DbGet $reader)
    {
        $this->db = $dbWriter;
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
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $isFinance = (int) ($data['is_finance'] ?? 0);

        if (!$warehouseNumber || !$productNumber || $quantity <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '仓库、产品和数量不能为空'];
        }

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

        $totalAmount = $unitPrice * $quantity;

        $productName = '';
        $supplierName = '';
        if ($isFinance) {
            $product = $this->reader->query(
                "SELECT q.name, s.name as supplier_name FROM supplier_quote q 
                 LEFT JOIN supplier_main s ON q.supplier_id = s.id
                 WHERE q.number = ? LIMIT 1",
                [$productNumber]
            );
            if (!empty($product)) {
                $productName = $product[0]['name'] ?? '';
                $supplierName = $product[0]['supplier_name'] ?? '';
            }
        }

        $insertData = [
            'warehouse_number' => $warehouseNumber,
            'product_number' => $productNumber,
            'direction' => $direction,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'before_quantity' => $beforeQuantity,
            'after_quantity' => $afterQuantity,
            'related_order_no' => $data['related_order_no'] ?? '',
            'operator' => $data['operator'] ?? '系统',
            'remark' => $data['remark'] ?? '未备注',
            'is_finance' => $isFinance,
        ];

        try {
            $this->db->insert('warehouse_io', $insertData);
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '写入进出库记录失败：' . $e->getMessage()];
        }

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

        if ($isFinance) {
            $this->syncFinanceFlow($direction, $productNumber, $productName, $supplierName, $quantity, $unitPrice, $totalAmount, $warehouseNumber);
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

    private function syncFinanceFlow($direction, $productNumber, $productName, $supplierName, $quantity, $unitPrice, $totalAmount, $warehouseNumber)
    {
        try {
            $flowType = $direction === 1 ? 1 : 3;
            $partyType = $direction === 1 ? 1 : 2;
            $directionVal = 1;

            $this->db->insert('finance_flow', [
                'flow_type' => $flowType,
                'direction' => $directionVal,
                'party_type' => $partyType,
                'party_number' => $productNumber,
                'party_name' => $supplierName ?: $productName,
                'related_number' => $warehouseNumber,
                'amount' => $totalAmount,
                'payment_method' => 4,
                'operator' => '系统',
                'remark' => $direction === 1 ? '采购入库' : '出库消耗',
            ]);
        } catch (\PDOException $e) {
            // 静默失败
        }
    }
}
