<?php
// finance/flow/delete.php
// 软删除资金流水记录

class SetterFinanceFlowDelete
{
    private $db;
    private $reader;

    public function __construct(DbSet $dbWriter, DbGet $dbReader)
    {
        $this->db = $dbWriter;
        $this->reader = $dbReader;
    }

    public function deleteFlow()
    {
        $rawBody = file_get_contents('php://input');
        $data = json_decode($rawBody, true);
        $number = (int) ($data['number'] ?? 0);

        if ($number <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => '流水编号无效'];
        }

        $result = $this->reader->query(
            "SELECT related_number, flow_type FROM finance_flow WHERE number = ? AND is_deleted = 0 LIMIT 1",
            [$number]
        );
        if (empty($result)) {
            http_response_code(404);
            return ['success' => false, 'message' => '流水记录不存在'];
        }

        try {
            $this->db->update('finance_flow', ['is_deleted' => 1], ['number' => $number]);
        } catch (\PDOException $e) {
            http_response_code(500);
            return ['success' => false, 'message' => '删除失败：' . $e->getMessage()];
        }

        return ['success' => true, 'message' => '删除成功'];
    }
}
