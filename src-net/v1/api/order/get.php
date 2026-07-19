<?php
class GetterOrder
{

    private $db;

    public function __construct(DbGet $dbReader)
    {
        $this->db = $dbReader;
    }

    public function getList($page, $size)
    {
        return ['page' => (int) $page, 'size' => (int) $size];
    }
    public function getDetails($order)
    {
        return ['order' => $order];
    }
}