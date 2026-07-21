<?php
// warehouse/inventory/checkProduct.php
// 检查商品是否在指定仓库有库存记录

class GetterWarehouseCheckProduct
{
    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function checkProduct()
    {
        $productNumber = isset($_GET['product_number']) ? (int) $_GET['product_number'] : 0;
        $warehouseNumber = isset($_GET['warehouse_number']) ? (int) $_GET['warehouse_number'] : 0;

        if ($productNumber <= 0) {
            return ['success' => false, 'message' => '商品编号不能为空'];
        }

        $result = [
            'success' => true,
            'exists_in_warehouse' => false,
            'exists_in_any_warehouse' => false,
            'warehouse_stock' => 0,
        ];

        if ($warehouseNumber > 0) {
            $row = $this->db->query(
                "SELECT quantity FROM warehouse_inventory WHERE product_number = ? AND warehouse_number = ? LIMIT 1",
                [$productNumber, $warehouseNumber]
            );
            if (!empty($row)) {
                $result['exists_in_warehouse'] = true;
                $result['warehouse_stock'] = (int) $row[0]['quantity'];
            }
        }

        $anyRow = $this->db->query(
            "SELECT quantity FROM warehouse_inventory WHERE product_number = ? LIMIT 1",
            [$productNumber]
        );
        if (!empty($anyRow)) {
            $result['exists_in_any_warehouse'] = true;
        }

        return $result;
    }
}