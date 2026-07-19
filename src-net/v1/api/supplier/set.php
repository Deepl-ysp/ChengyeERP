<?php
class SetterSupplier
{
    private $db;

    function __construct(DbSet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function setSupplier(...$args)
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
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? 'exp@exp.com',
            'address' => $data['address'] ?? '未知地址',
            'contact' => $data['contact'] ?? '未知联系人',
            'phone_number' => $data['phone_number'] ?? ' ',
            'status' => $data['status'] ?? 0,
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $id = $this->db->insert('supplier_main', $insertData);
            return [
                'success' => true,
                'message' => '供应商创建成功',
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
            'name' => $data['name'] ?? '未设置',
            'email' => $data['email'] ?? 'exp@exp.com',
            'address' => $data['address'] ?? '未知地址',
            'contact' => $data['contact'] ?? '未知联系人',
            'phone_number' => $data['phone_number'] ?? ' ',
            'status' => $data['status'] ?? 0,
            'remark' => $data['remark'] ?? '未备注',
        ];

        try {
            $affected = $this->db->update('supplier_main', $updateData, ['id' => $id]);
            if ($affected === 0) {
                return [
                    'success' => true,
                    'message' => '更新成功（无变化）',
                    'data' => ['id' => $id]
                ];
            }
            return [
                'success' => true,
                'message' => '供应商更新成功',
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
