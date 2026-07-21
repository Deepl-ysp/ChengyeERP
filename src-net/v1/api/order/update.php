<?php
// order/update.php
// 修改订单（全量覆盖，含库存回滚重扣）

class UpdaterOrder
{
    private $db;
    private $reader;
    private $orderConfig;

    public function __construct(DbSet $dbWriter, DbGet $dbReader)
    {
        $this->db = $dbWriter;
        $this->reader = $dbReader;
        $this->orderConfig = include __DIR__ . '/../config/order.config.php';
    }

    public function updateOrder(...$args)
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        $orderNumber = (int) ($data['number'] ?? 0);
        if ($orderNumber <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单编号无效'];
        }

        // 查原订单
        $oldOrder = $this->reader->query(
            "SELECT * FROM order_main WHERE number = ? AND is_deleted = 0 LIMIT 1",
            [$orderNumber]
        );
        if (empty($oldOrder)) {
            http_response_code(404);
            return ['success' => false, 'message' => '订单不存在'];
        }
        $oldOrder = $oldOrder[0];

        // ========== 1. 参数校验 ==========
        $customerName = $data['customer_name'] ?? '未填写';
        $customerNumber = (int) ($data['customer_number'] ?? 0);
        $sellerName = $data['seller_name'] ?? '未填写';
        $receiverName = $data['receiver_name'] ?? '未填写';
        $receiverAddress = $data['receiver_address'] ?? '未填写';
        $receiverPhone = $data['receiver_phone'] ?? '未填写';
        $remark = $data['remark'] ?? '';
        $orderStatus = (int) ($data['order_status'] ?? $oldOrder['order_status']);
        $items = $data['items'] ?? [];
        $imageBase64 = $data['image_path'] ?? '';

        if (empty($items)) {
            http_response_code(400);
            return ['success' => false, 'message' => '订单商品不能为空'];
        }

        // 计算总数和总金额
        $totalQuantity = 0;
        $totalAmount = 0;
        foreach ($items as &$item) {
            $item['quantity'] = (float) $item['quantity'];
            $item['unit_price'] = (float) $item['unit_price'];
            $item['subtotal'] = $item['quantity'] * $item['unit_price'];
            $totalQuantity += $item['quantity'];
            $totalAmount += $item['subtotal'];
        }
        unset($item);

        // 已支付金额处理：如果总额变小，已付金额不能超过总额
        $paidAmount = (float) $oldOrder['paid_amount'];
        if ($paidAmount > $totalAmount) {
            // 自动调整已付金额为总额（相当于部分退款场景需单独处理）
            $paidAmount = $totalAmount;
        }

        // 重新计算支付状态
        $paymentStatus = 0; // 未支付
        if ($paidAmount > 0 && $paidAmount < $totalAmount) {
            $paymentStatus = 1; // 部分支付
        } elseif ($paidAmount >= $totalAmount && $totalAmount > 0) {
            $paymentStatus = 2; // 全额支付
        }

        // ========== 2. 回滚原库存扣减 ==========
        $this->rollbackOldDeductions($orderNumber);

        // ========== 3. 重新扣减库存 ==========
        $deductionResult = $this->deductInventory($items, $orderNumber);
        if (!$deductionResult['success']) {
            http_response_code(400);
            return ['success' => false, 'message' => $deductionResult['message']];
        }
        $deductionDetails = $deductionResult['details'];

        // ========== 4. 保存新订单图片（如果传了新图片）==========
        $imagePath = $oldOrder['image_path'];
        if (!empty($imageBase64) && strpos($imageBase64, 'data:image') === 0) {
            $imagePath = $this->saveOrderImage($imageBase64, $orderNumber);
        }

        // ========== 5. 更新订单主表 ==========
        $finalRemark = $remark;
        $orderType = $data['order_type'] ?? 'supply';
        if (!empty($remark)) {
            $finalRemark = '[' . ($orderType === 'supply' ? '供货' : '零售') . '] ' . $remark;
        } else {
            $finalRemark = '[' . ($orderType === 'supply' ? '供货' : '零售') . ']';
        }

        $orderData = [
            'customer_name' => $customerName,
            'customer_number' => $customerNumber,
            'seller_name' => $sellerName,
            'receiver_name' => $receiverName,
            'receiver_address' => $receiverAddress,
            'receiver_phone' => $receiverPhone,
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
            'order_status' => $orderStatus,
            'remark' => $finalRemark,
            'image_path' => $imagePath,
        ];

        try {
            $this->db->update('order_main', $orderData, ['number' => $orderNumber]);
        } catch (\PDOException $e) {
            // 订单更新失败，回滚库存
            $this->rollbackDeduction($deductionDetails);
            http_response_code(500);
            return ['success' => false, 'message' => '订单更新失败：' . $e->getMessage()];
        }

        // ========== 6. 删除原商品明细和扣减明细，重新写入 ==========
        try {
            // 删除原扣减明细
            $this->db->delete('order_item_deduction', ['order_number' => $orderNumber]);
            // 删除原商品明细
            $this->db->delete('order_item', ['order_number' => $orderNumber]);

            // 重新写入
            foreach ($items as $idx => $item) {
                $itemData = [
                    'order_number' => $orderNumber,
                    'product_name' => $item['product_name'],
                    'product_number' => $deductionDetails[$idx]['main_product_number'] ?? 0,
                    'unit' => $item['unit'] ?? '件',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'specification' => $item['specification'] ?? '',
                    'warehouse_number' => $deductionDetails[$idx]['main_warehouse_number'] ?? 0,
                    'remark' => '',
                ];
                $itemId = $this->db->insert('order_item', $itemData);

                if (!empty($deductionDetails[$idx]['deductions'])) {
                    foreach ($deductionDetails[$idx]['deductions'] as $deduction) {
                        $this->db->insert('order_item_deduction', [
                            'order_item_id' => $itemId,
                            'order_number' => $orderNumber,
                            'product_number' => $deduction['product_number'],
                            'warehouse_number' => $deduction['warehouse_number'],
                            'deducted_quantity' => $deduction['deducted_quantity'],
                            'unit_cost' => $deduction['unit_cost'] ?? 0,
                        ]);
                    }
                }
            }
        } catch (\PDOException $e) {
            // 明细写入失败，回滚库存
            $this->rollbackDeduction($deductionDetails);
            http_response_code(500);
            return ['success' => false, 'message' => '订单明细写入失败：' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => '订单修改成功',
            'data' => [
                'order_number' => $orderNumber,
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
                'payment_status' => $paymentStatus,
            ],
        ];
    }

    /**
     * 回滚原订单的所有库存扣减
     */
    private function rollbackOldDeductions(int $orderNumber): void
    {
        // 查所有扣减明细
        $deductions = $this->reader->query(
            "SELECT d.product_number, d.warehouse_number, d.deducted_quantity
             FROM order_item_deduction d
             WHERE d.order_number = ?",
            [$orderNumber]
        );

        foreach ($deductions as $d) {
            // 恢复库存
            $inv = $this->reader->query(
                "SELECT id, quantity FROM warehouse_inventory
                 WHERE product_number = ? AND warehouse_number = ?
                 LIMIT 1",
                [$d['product_number'], $d['warehouse_number']]
            );

            if (!empty($inv[0])) {
                $currentQty = (float) $inv[0]['quantity'];
                $this->db->update(
                    'warehouse_inventory',
                    ['quantity' => $currentQty + (float) $d['deducted_quantity']],
                    ['id' => $inv[0]['id']]
                );
            }
        }
    }

    /**
     * 库存扣减：按名称+规格匹配多个产品凑单
     * （逻辑同 set.php）
     */
    private function deductInventory(array $items, int $orderNumber): array
    {
        $details = [];
        $allDeductions = [];

        foreach ($items as $idx => $item) {
            $productName = $item['product_name'] ?? '';
            $specification = $item['specification'] ?? '';
            $needQty = (float) $item['quantity'];

            if ($needQty <= 0 || empty($productName)) {
                $details[$idx] = [
                    'main_product_number' => 0,
                    'main_warehouse_number' => 0,
                    'deductions' => [],
                ];
                continue;
            }

            $sql = "SELECT i.id, i.warehouse_number, i.product_number, i.quantity,
                           q.unit
                    FROM warehouse_inventory i
                    LEFT JOIN supplier_quote q ON i.product_number = q.number
                    WHERE q.name = ? AND q.specification = ? AND i.quantity > 0
                    ORDER BY i.quantity ASC";

            $matchedProducts = $this->reader->query($sql, [$productName, $specification]);

            if (empty($matchedProducts)) {
                $this->rollbackDeduction($allDeductions);
                return [
                    'success' => false,
                    'message' => "产品「{$productName}" . ($specification ? "（{$specification}）" : '') . "」库存不足",
                ];
            }

            $totalAvailable = 0;
            foreach ($matchedProducts as $p) {
                $totalAvailable += (float) $p['quantity'];
            }

            if ($totalAvailable < $needQty) {
                $this->rollbackDeduction($allDeductions);
                return [
                    'success' => false,
                    'message' => "产品「{$productName}" . ($specification ? "（{$specification}）" : '') . "」库存不足，需要 {$needQty}，可用 {$totalAvailable}",
                ];
            }

            $itemDeductions = [];
            $remaining = $needQty;
            $mainProductNumber = 0;
            $mainWarehouseNumber = 0;

            foreach ($matchedProducts as $p) {
                if ($remaining <= 0) break;

                $available = (float) $p['quantity'];
                $deductQty = min($available, $remaining);

                $newQty = $available - $deductQty;
                $this->db->update(
                    'warehouse_inventory',
                    ['quantity' => $newQty],
                    ['id' => $p['id']]
                );

                $deductionRecord = [
                    'product_number' => $p['product_number'],
                    'warehouse_number' => $p['warehouse_number'],
                    'deducted_quantity' => $deductQty,
                    'unit_cost' => 0,
                    'inventory_id' => $p['id'],
                ];
                $itemDeductions[] = $deductionRecord;
                $allDeductions[] = $deductionRecord;

                if ($mainProductNumber === 0) {
                    $mainProductNumber = $p['product_number'];
                    $mainWarehouseNumber = $p['warehouse_number'];
                }

                $remaining -= $deductQty;
            }

            $details[$idx] = [
                'main_product_number' => $mainProductNumber,
                'main_warehouse_number' => $mainWarehouseNumber,
                'deductions' => $itemDeductions,
            ];
        }

        return ['success' => true, 'details' => $details];
    }

    /**
     * 回滚库存扣减
     */
    private function rollbackDeduction(array $deductions): void
    {
        foreach ($deductions as $d) {
            if (!empty($d['inventory_id'])) {
                $inv = $this->reader->query(
                    "SELECT quantity FROM warehouse_inventory WHERE id = ?",
                    [$d['inventory_id']]
                );
                if (!empty($inv[0]['quantity'])) {
                    $currentQty = (float) $inv[0]['quantity'];
                    $this->db->update(
                        'warehouse_inventory',
                        ['quantity' => $currentQty + $d['deducted_quantity']],
                        ['id' => $d['inventory_id']]
                    );
                }
            }
        }
    }

    /**
     * 保存订单图片（base64 转文件）
     */
    private function saveOrderImage(string $base64Data, int $orderNumber): string
    {
        $config = $this->orderConfig;

        if (strpos($base64Data, 'data:image') === 0) {
            $commaPos = strpos($base64Data, ',');
            if ($commaPos !== false) {
                $base64Data = substr($base64Data, $commaPos + 1);
            }
        }

        if (strlen($base64Data) > $config['max_base64_size']) {
            return '';
        }

        $imageData = base64_decode($base64Data);
        if ($imageData === false) {
            return '';
        }

        $date = date($config['date_format']);
        $dir = rtrim($config['image_base_path'], '\\/') . '\\' . $date;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = str_replace(
            ['{date}', '{order_number}'],
            [$date, $orderNumber],
            $config['filename_format']
        );
        $filePath = $dir . '\\' . $filename;

        file_put_contents($filePath, $imageData);

        return $filePath;
    }
}