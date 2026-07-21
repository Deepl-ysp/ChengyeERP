<?php
// finance/payable/get.php
// 应付账款查询

class GetterFinancePayable
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList(...$args)
    {
        $page = 1;
        $size = 10;
        if (count($args) >= 2) {
            $page = (int) $args[0];
            $size = (int) $args[1];
        } elseif (count($args) === 1 && is_array($args[0])) {
            $page = (int) ($args[0]['page'] ?? 1);
            $size = (int) ($args[0]['size'] ?? 10);
        }

        $page = (int) ($_GET['page'] ?? $page);
        $size = (int) ($_GET['size'] ?? $size);
        $page = max(1, $page);
        $size = max(1, min(100, $size));
        $offset = ($page - 1) * $size;

        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $supplierNumber = isset($_GET['supplier_number']) ? $_GET['supplier_number'] : '';

        // 应付账款基于 finance_flow 表中 flow_type=1 的记录
        $where = ["f.is_deleted = 0", "f.flow_type = 1"];
        $params = [];

        if ($keyword !== '') {
            $where[] = "(f.party_name LIKE ? OR f.remark LIKE ?)";
            $kw = "%$keyword%";
            $params[] = $kw;
            $params[] = $kw;
        }
        if ($supplierNumber !== '') {
            $where[] = "f.party_number = ?";
            $params[] = (int) $supplierNumber;
        }

        $whereSql = implode(' AND ', $where);

        $countResult = $this->db->query(
            "SELECT COUNT(*) AS total FROM finance_flow f WHERE $whereSql",
            $params
        );
        $total = (int) ($countResult[0]['total'] ?? 0);

        $sumResult = $this->db->query(
            "SELECT COALESCE(SUM(f.amount), 0) AS paid_amount
             FROM finance_flow f WHERE $whereSql",
            $params
        );
        $sumPaid = (float) ($sumResult[0]['paid_amount'] ?? 0);

        $list = $this->db->query(
            "SELECT f.id, f.number, f.party_number AS supplier_number, f.party_name AS supplier_name,
                f.amount AS paid_amount, f.payment_method, f.flow_time, f.operator, f.remark,
                f.related_number
             FROM finance_flow f
             WHERE $whereSql
             ORDER BY f.flow_time DESC, f.id DESC
             LIMIT $offset, $size",
            $params
        );

        // 按供应商汇总
        $bySupplier = $this->db->query(
            "SELECT f.party_number AS supplier_number, f.party_name AS supplier_name,
                COUNT(*) AS payment_count,
                COALESCE(SUM(f.amount), 0) AS paid_amount
             FROM finance_flow f
             WHERE f.is_deleted = 0 AND f.flow_type = 1
             GROUP BY f.party_number, f.party_name
             ORDER BY paid_amount DESC
             LIMIT 100"
        );

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size,
            'sum_paid' => $sumPaid,
            'by_supplier' => $bySupplier,
        ];
    }
}
