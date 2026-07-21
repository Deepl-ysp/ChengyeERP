<?php
// order/set.php
// 创建订单（含库存扣减 + 图片保存）

class SetterOrder
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

    public function setOrder(...$args)
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的JSON数据'];
        }

        // ========== 1. 参数校验 ==========
        $customerName = $data['customer_name'] ?? '未填写';
        $customerNumber = (int) ($data['customer_number'] ?? 0);
        $sellerName = $data['seller_name'] ?? '未填写';
        $receiverName = $data['receiver_name'] ?? '未填写';
        $receiverAddress = $data['receiver_address'] ?? '未填写';
        $receiverPhone = $data['receiver_phone'] ?? '未填写';
        $remark = $data['remark'] ?? '';
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

        // ========== 2. 生成订单编号 ==========
        $orderNumber = $this->generateOrderNumber();

        // ========== 3. 库存扣减（按名称+规格匹配多个产品凑单）==========
        // 返回扣减明细或错误信息
        $deductionResult = $this->deductInventory($items, $orderNumber);
        if (!$deductionResult['success']) {
            http_response_code(400);
            return ['success' => false, 'message' => $deductionResult['message']];
        }
        $deductionDetails = $deductionResult['details']; // 每个订单项的扣减明细

        // ========== 4. 保存订单图片 ==========
        $imagePath = '';
        if (!empty($imageBase64)) {
            $imagePath = $this->saveOrderImage($imageBase64, $orderNumber);
        }

        // ========== 5. 写入订单主表 ==========
        // remark 字段同时存储订单类型（供货/零售）
        $finalRemark = $remark;
        $orderType = $data['order_type'] ?? 'supply';
        if (!empty($remark)) {
            $finalRemark = '[' . ($orderType === 'supply' ? '供货' : '零售') . '] ' . $remark;
        } else {
            $finalRemark = '[' . ($orderType === 'supply' ? '供货' : '零售') . ']';
        }

        $orderData = [
            'number' => $orderNumber,
            'customer_name' => $customerName,
            'customer_number' => $customerNumber,
            'seller_name' => $sellerName,
            'receiver_name' => $receiverName,
            'receiver_address' => $receiverAddress,
            'receiver_phone' => $receiverPhone,
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'payment_status' => 0, // 未支付
            'order_status' => 0,   // 待处理
            'remark' => $finalRemark,
            'image_path' => $imagePath,
            'is_deleted' => 0,
        ];

        try {
            $this->db->insert('order_main', $orderData);
        } catch (\PDOException $e) {
            // 订单写入失败，需要回滚库存扣减
            $this->rollbackDeduction($deductionDetails);
            http_response_code(500);
            return ['success' => false, 'message' => '订单创建失败：' . $e->getMessage()];
        }

        // ========== 6. 写入订单商品明细 + 扣减明细 ==========
        try {
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

                // 写入该订单项的扣减明细
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
            // 明细写入失败，回滚订单和库存
            $this->db->update('order_main', ['is_deleted' => 1], ['number' => $orderNumber]);
            $this->rollbackDeduction($deductionDetails);
            http_response_code(500);
            return ['success' => false, 'message' => '订单明细写入失败：' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => '订单创建成功',
            'data' => [
                'order_number' => $orderNumber,
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
                'image_path' => $imagePath,
            ],
        ];
    }

    /**
     * 生成订单编号（毫秒时间戳 + 随机数）
     */
    private function generateOrderNumber(): int
    {
        return (int) (round(microtime(true) * 1000) * 10000 + mt_rand(0, 9999));
    }

    /**
     * 库存扣减：按名称+规格匹配多个产品凑单
     *
     * @param array $items 订单商品列表
     * @param int $orderNumber 订单编号
     * @return array ['success' => bool, 'message' => string, 'details' => array]
     */
    private function deductInventory(array $items, int $orderNumber): array
    {
        $details = [];
        $allDeductions = []; // 用于回滚

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

            // 按名称+规格匹配所有有库存的产品（JOIN supplier_quote 获取名称和规格）
            // 按库存数量从小到大排序（先扣完小库存的，避免碎片化）
            // 实际业务可调整为从大到小或其他策略
            $sql = "SELECT i.id, i.warehouse_number, i.product_number, i.quantity,
                           q.unit
                    FROM warehouse_inventory i
                    LEFT JOIN supplier_quote q ON i.product_number = q.number
                    WHERE q.name = ? AND q.specification = ? AND i.quantity > 0
                    ORDER BY i.quantity ASC";

            $matchedProducts = $this->reader->query($sql, [$productName, $specification]);

            if (empty($matchedProducts)) {
                // 库存不足
                $this->rollbackDeduction($allDeductions);
                return [
                    'success' => false,
                    'message' => "产品「{$productName}" . ($specification ? "（{$specification}）" : '') . "」库存不足",
                ];
            }

            // 计算总可用库存
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

            // 依次扣减直到凑够数量
            $itemDeductions = [];
            $remaining = $needQty;
            $mainProductNumber = 0;
            $mainWarehouseNumber = 0;

            foreach ($matchedProducts as $p) {
                if ($remaining <= 0) break;

                $available = (float) $p['quantity'];
                $deductQty = min($available, $remaining);

                // 更新库存
                $newQty = $available - $deductQty;
                $this->db->update(
                    'warehouse_inventory',
                    ['quantity' => $newQty],
                    ['id' => $p['id']]
                );

                // 记录扣减明细
                $deductionRecord = [
                    'product_number' => $p['product_number'],
                    'warehouse_number' => $p['warehouse_number'],
                    'deducted_quantity' => $deductQty,
                    'unit_cost' => 0,
                    'inventory_id' => $p['id'], // 用于回滚
                ];
                $itemDeductions[] = $deductionRecord;
                $allDeductions[] = $deductionRecord;

                // 第一个被扣减的作为主产品
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
                // 查当前库存
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
     *
     * @param string $base64Data base64 图片数据（含或不含 data:image/png;base64, 前缀）
     * @param int $orderNumber 订单编号
     * @return string 图片绝对路径
     */
    private function saveOrderImage(string $base64Data, int $orderNumber): string
    {
        $config = $this->orderConfig;

        // 去除 data URI 前缀
        if (strpos($base64Data, 'data:image') === 0) {
            $commaPos = strpos($base64Data, ',');
            if ($commaPos !== false) {
                $base64Data = substr($base64Data, $commaPos + 1);
            }
        }

        // 校验数据大小
        if (strlen($base64Data) > $config['max_base64_size']) {
            return '';
        }

        $imageData = base64_decode($base64Data);
        if ($imageData === false) {
            return '';
        }

        // 生成保存路径：根目录/日期/日期-订单号.png
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
