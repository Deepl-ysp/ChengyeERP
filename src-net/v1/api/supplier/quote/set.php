<?php
class SetterQuote
{
    private $db;

    public function __construct(DbSet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function setQuote(...$args)
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data) {
            http_response_code(400);
            return ['error' => '无效的JSON数据'];
        }

        // 如果包含 id，则为更新
        if (isset($data['id']) && $data['id'] > 0) {
            return $this->update($data);
        } else {
            return $this->insert($data);
        }
    }

    private function insert($data)
    {
        $insertData = [
            'supplier_id' => (int) ($data['supplier_id'] ?? 0),
            'name' => $data['name'] ?? '未设置',
            'unit_price' => $data['unit_price'] ?? 0,
            'unit' => $data['unit'] ?? '件',
            'specification' => (string)($data['specification'] ?? ''),
        ];

        try {
            $id = $this->db->insert('supplier_quote', $insertData);
            return [
                'success' => true,
                'message' => '货物创建成功',
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
            'supplier_id' => (int) ($data['supplier_id'] ?? 0),
            'name' => $data['name'] ?? '未设置',
            'unit_price' => $data['unit_price'] ?? 0,
            'unit' => $data['unit'] ?? '件',
            'specification' => (string)($data['specification'] ?? ''),
        ];

        try {
            $affected = $this->db->update('supplier_quote', $updateData, ['id' => $id]);
            if ($affected === 0) {
                // 可能数据未变化或记录不存在
                return [
                    'success' => true,
                    'message' => '更新成功（无变化）',
                    'data' => ['id' => $id]
                ];
            }
            return [
                'success' => true,
                'message' => '货物更新成功',
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