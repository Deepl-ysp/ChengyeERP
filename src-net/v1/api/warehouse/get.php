<?php
class GetterWarehouse
{
    private $db;

    function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList($route, $params)
    {
        $page = (int) ($params['page'] ?? 1) ?: 1;
        $size = (int) ($params['size'] ?? 10) ?: 10;
        $offset = ($page - 1) * $size;

        $list = $this->db->getList(
            'warehouse',
            [],
            '*',
            'id DESC',
            $size,
            $offset
        );

        $total = $this->db->getCount('warehouse');

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'size' => $size
        ];
    }
}
