<?php
class GetterSupplier
{
    private $db;

    function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList($route, $params)
    {
        // Mezon Router 传入 ($route, $params)，从中提取分页参数
        $page = (int) ($params['page'] ?? 1) ?: 1;
        $size = (int) ($params['size'] ?? 10) ?: 10;
        $offset = ($page - 1) * $size;

        // 查询列表
        $list = $this->db->getList(
            'supplier_main',
            [],          // 无条件
            '*',         // 所有字段
            'id DESC',   // 按ID降序
            $size,
            $offset
        );

        // 统计总数
        $total = $this->db->getCount('supplier_main');

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ];
    }
}
