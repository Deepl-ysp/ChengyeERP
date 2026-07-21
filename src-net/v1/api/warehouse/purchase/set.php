<?php
// warehouse/purchase/set.php
// 采购单创建与入库确认

class SetterWarehousePurchase
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbWriter, DbGet $dbReader)
    {
        $this->db = $dbWriter;
        $this->reader = $dbReader;
    }

    public function setPurchase()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        $supplierNumber = (int) ($data['supplier_number'] ?? 0);
        $supplierName = $data['supplier_name'] ?? '';
        $warehouseNumber = (int) ($data['warehouse_number'] ?? 0);
        $warehouseName = $data['warehouse_name'] ?? '';
        $items = $data['items'] ?? [];
        $operator = $data['operator'] ?? 'admin';
        $remark = $data['remark'] ?? '';

        if (!$supplierNumber || !$warehouseNumber || empty($items)) {
            http_response_code(400);
            return ['success' => false, 'message' => '供应商、仓库和采购商品不能为空'];
        }

        $totalAmount = 0;
        foreach ($items as $item) {
            $price = (float) ($item['unit_price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $totalAmount += $price * $qty;
        }

        try {
            $this->db->insert('purchase_order', [
                'supplier_number' => $supplierNumber,
                'supplier_name' => $supplierName,
                'warehouse_number' => $warehouseNumber,
                'warehouse_name' => $warehouseName,
                'total_amount' => $totalAmount,
                'status' => 0,
                'operator' => $operator,
                'remark' => $remark,
            ]);

            $lastId = $this->reader->query("SELECT LAST_INSERT_ID() AS id");
            $orderNumber = 0;
            if (!empty($lastId)) {
                $order = $this->reader->query("SELECT number FROM purchase_order WHERE id = ?", [(int) $lastId[0]['id']]);
                $orderNumber = (int) ($order[0]['number'] ?? 0);
            }

            foreach ($items as $item) {
                $price = (float) ($item['unit_price'] ?? 0);
                $qty = (int) ($item['quantity'] ?? 0);
                $this->db->insert('purchase_item', [
                    'purchase_number' => $orderNumber,
                    'product_number' => (int) ($item['product_number'] ?? 0),
                    'product_name' => $item['product_name'] ?? '',
                    'unit_price' => $price,
                    'quantity' => $qty,
                    'total_amount' => $price * $qty,
                    'warehouse_number' => $warehouseNumber,
                    'status' => 0,
                    'remark' => $item['remark'] ?? '',
                ]);
            }

            return [
                'success' => true,
                'message' => '采购单创建成功',
                'data' => ['purchase_number' => $orderNumber],
            ];
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '创建采购单失败：' . $e->getMessage()];
        }
    }

    public function confirmPurchase()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        $purchaseNumber = (int) ($data['purchase_number'] ?? 0);
        $operator = $data['operator'] ?? 'admin';

        if ($purchaseNumber <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '采购单号不能为空'];
        }

        $po = $this->reader->query(
            "SELECT * FROM purchase_order WHERE number = ? AND is_deleted = 0 LIMIT 1",
            [$purchaseNumber]
        );
        if (empty($po)) {
            http_response_code(404);
            return ['success' => false, 'message' => '采购单不存在'];
        }

        $order = $po[0];
        if ((int) $order['status'] !== 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '采购单状态不允许确认'];
        }

        try {
            $this->db->update('purchase_order', [
                'status' => 1,
                'confirm_time' => date('Y-m-d H:i:s'),
            ], ['number' => $purchaseNumber]);

            return ['success' => true, 'message' => '采购单已确认'];
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '确认失败：' . $e->getMessage()];
        }
    }

    public function completePurchase()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        $purchaseNumber = (int) ($data['purchase_number'] ?? 0);
        $operator = $data['operator'] ?? 'admin';

        if ($purchaseNumber <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '采购单号不能为空'];
        }

        $po = $this->reader->query(
            "SELECT * FROM purchase_order WHERE number = ? AND is_deleted = 0 LIMIT 1",
            [$purchaseNumber]
        );
        if (empty($po)) {
            http_response_code(404);
            return ['success' => false, 'message' => '采购单不存在'];
        }

        $order = $po[0];
        $currentStatus = (int) $order['status'];

        if ($currentStatus === 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '请先确认采购单'];
        }

        try {
            $newStatus = $currentStatus === 1 ? 2 : 3;

            if ($newStatus === 2) {
                $items = $this->reader->query(
                    "SELECT * FROM purchase_item WHERE purchase_number = ? AND status = 0",
                    [$purchaseNumber]
                );

                foreach ($items as $item) {
                    $productNumber = (int) $item['product_number'];
                    $quantity = (int) $item['quantity'];
                    $warehouseNumber = (int) $item['warehouse_number'];

                    $existing = $this->reader->query(
                        "SELECT * FROM warehouse_inventory WHERE product_number = ? AND warehouse_number = ? LIMIT 1",
                        [$productNumber, $warehouseNumber]
                    );

                    if (!empty($existing)) {
                        $currentQty = (int) $existing[0]['quantity'];
                        $this->db->update('warehouse_inventory', [
                            'quantity' => $currentQty + $quantity,
                            'last_update_time' => date('Y-m-d H:i:s'),
                        ], ['product_number' => $productNumber, 'warehouse_number' => $warehouseNumber]);
                    }

                    $this->db->update('purchase_item', ['status' => 1], ['id' => (int) $item['id']]);
                }
            }

            $updateData = ['status' => $newStatus];
            if ($newStatus === 2) {
                $updateData['complete_time'] = date('Y-m-d H:i:s');
            }

            $this->db->update('purchase_order', $updateData, ['number' => $purchaseNumber]);

            $message = $newStatus === 2 ? '采购单已入库' : '采购单已完成';
            return ['success' => true, 'message' => $message];
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '操作失败：' . $e->getMessage()];
        }
    }
}
