<?php
// warehouse/inventory/searchProducts.php
// 按名称+规格聚合库存搜索（用于创建订单时选品）
// 供货模式传 customer_number，会 LEFT JOIN customer_price 带出该客户的有效协议价
// 零售模式不传 customer_number，价格留空（前端手填）

class GetterWarehouseSearchProducts
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function searchProducts()
    {
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
        $customerNumber = isset($_GET['customer_number']) && $_GET['customer_number'] !== '' ? (int) $_GET['customer_number'] : 0;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

        if ($keyword === '') {
            return ['list' => [], 'total' => 0];
        }

        if ($customerNumber > 0) {
            $sql = "SELECT q.name AS product_name,
                           q.specification,
                           q.unit,
                           COALESCE(SUM(i.quantity), 0) AS total_stock,
                           COUNT(DISTINCT i.product_number) AS supplier_count,
                           cp.unit_price,
                           CASE WHEN cp.unit_price IS NOT NULL THEN 1 ELSE 0 END AS has_customer_price
                    FROM supplier_quote q
                    LEFT JOIN warehouse_inventory i
                           ON i.product_number = q.number AND i.quantity > 0
                    LEFT JOIN (
                        SELECT product_name, specification, unit_price
                        FROM customer_price
                        WHERE customer_number = ?
                          AND status = 0
                          AND effective_date <= CURRENT_DATE
                          AND (expiry_date IS NULL OR expiry_date >= CURRENT_DATE)
                        ORDER BY effective_date DESC
                    ) cp ON cp.product_name = q.name AND cp.specification = q.specification
                    WHERE q.name LIKE ?
                    GROUP BY q.name, q.specification, q.unit, cp.unit_price
                    ORDER BY q.name ASC, q.specification ASC
                    LIMIT {$limit}";
            $kw = "%$keyword%";
            $list = $this->db->query($sql, [$customerNumber, $kw]);
        } else {
            $sql = "SELECT q.name AS product_name,
                           q.specification,
                           q.unit,
                           COALESCE(SUM(i.quantity), 0) AS total_stock,
                           COUNT(DISTINCT i.product_number) AS supplier_count,
                           0 AS unit_price,
                           0 AS has_customer_price
                    FROM supplier_quote q
                    LEFT JOIN warehouse_inventory i
                           ON i.product_number = q.number AND i.quantity > 0
                    WHERE q.name LIKE ?
                    GROUP BY q.name, q.specification, q.unit
                    ORDER BY q.name ASC, q.specification ASC
                    LIMIT {$limit}";
            $kw = "%$keyword%";
            $list = $this->db->query($sql, [$kw]);
        }

        foreach ($list as &$row) {
            $row['total_stock'] = (float) $row['total_stock'];
            $row['supplier_count'] = (int) $row['supplier_count'];
            $row['unit_price'] = $row['unit_price'] === null ? 0 : (float) $row['unit_price'];
            $row['has_customer_price'] = (int) $row['has_customer_price'] === 1;
        }
        unset($row);

        return [
            'list' => $list,
            'total' => count($list),
        ];
    }
}