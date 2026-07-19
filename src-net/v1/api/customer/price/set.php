<?php
class SetterCustomerPrice
{
    private $db;

    public function __construct(DbSet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function setPrice(...$args)
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
            'customer_number' => (int) ($data['customer_number'] ?? 0),
            'product_name' => $data['product_name'] ?? '未设置',
            'specification' => $data['specification'] ?? '',
            'unit_price' => $data['unit_price'] ?? 0,
            'unit' => $data['unit'] ?? '件',
            'effective_date' => $data['effective_date'] ?? date('Y-m-d'),
            'expiry_date' => $data['expiry_date'] ?? null,
            'status' => $data['status'] ?? 0,
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $id = $this->db->insert('customer_price', $insertData);
            return [
                'success' => true,
                'message' => '客户价格创建成功',
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
            'customer_number' => (int) ($data['customer_number'] ?? 0),
            'product_name' => $data['product_name'] ?? '未设置',
            'specification' => $data['specification'] ?? '',
            'unit_price' => $data['unit_price'] ?? 0,
            'unit' => $data['unit'] ?? '件',
            'effective_date' => $data['effective_date'] ?? date('Y-m-d'),
            'expiry_date' => $data['expiry_date'] ?? null,
            'status' => $data['status'] ?? 0,
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $affected = $this->db->update('customer_price', $updateData, ['id' => $id]);
            if ($affected === 0) {
                return [
                    'success' => true,
                    'message' => '更新成功（无变化）',
                    'data' => ['id' => $id]
                ];
            }
            return [
                'success' => true,
                'message' => '客户价格更新成功',
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
