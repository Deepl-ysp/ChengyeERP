<?php
class GetterCustomer
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

        $list = $this->db->getList(
            'customer',
            [],
            '*',
            'id DESC',
            $size,
            $offset
        );

        $total = $this->db->getCount('customer');

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ];
    }
}
