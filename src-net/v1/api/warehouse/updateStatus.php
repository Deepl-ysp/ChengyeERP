<?php
class UpdateWarehouseStatus
{
    private $db;

    public function __construct(DbSet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function updateStatus()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);

        if (!$data || !isset($data['number']) || !isset($data['status'])) {
            http_response_code(400);
            return ['success' => false, 'message' => '缺少编号或状态参数'];
        }

        $number = $data['number'];
        $status = (int) $data['status'];

        if (!in_array($status, [0, 1, 2], true)) {
            http_response_code(400);
            return ['success' => false, 'message' => '无效的状态值（0=启用, 1=禁用, 2=异常）'];
        }

        try {
            $affected = $this->db->update('warehouse', ['status' => $status], ['number' => $number]);
            if ($affected === 0) {
                return [
                    'success' => true,
                    'message' => '状态未变化',
                ];
            }
            $statusText = ['启用', '禁用', '异常'];
            return [
                'success' => true,
                'message' => "仓库已{$statusText[$status]}"
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
