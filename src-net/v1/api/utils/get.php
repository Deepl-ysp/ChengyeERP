<?php

/**
 * 通用 MySQL 读取类（基于 PDO）
 *
 * 支持：
 * - 单条记录 (getOne)
 * - 多条记录 (getList)
 * - 分页记录 (getPage)
 * - 统计数量 (getCount)
 * - 单个字段值 (getValue)
 * - 单列数据 (getColumn)
 * - 自定义 SQL (query)
 *
 * 条件数组支持：
 * - 简单等值：['name' => 'John', 'age' => 25]
 * - 比较操作：['age >' => 18, 'score >= ' => 80]
 * - IN 查询：['id' => [1,2,3]] 自动转为 IN
 * - LIKE 查询：['name LIKE' => '%Tom%'] （自行包含 %）
 * - 多条件组合：支持 AND 连接
 *
 * 排序：字符串或数组，如 'id DESC' 或 ['id DESC', 'name ASC']
 * 分页：page 从 1 开始
 */
class DbGet
{
    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var int 默认 PDO 获取模式
     */
    protected $fetchMode = \PDO::FETCH_ASSOC;

    /**
     * 构造函数
     *
     * @param \PDO $pdo 已连接的有效 PDO 实例
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 设置 PDO 获取模式
     *
     * @param int $mode
     * @return $this
     */
    public function setFetchMode(int $mode)
    {
        $this->fetchMode = $mode;
        return $this;
    }

    /**
     * 获取单条记录
     *
     * @param string $table 表名
     * @param array  $where 条件数组
     * @param string $fields 要查询的字段，默认 *
     * @param string|array|null $order 排序，如 'id DESC' 或 ['id ASC', 'name DESC']
     * @return array|false 关联数组或 false
     */
    public function getOne(string $table, array $where = [], string $fields = '*', $order = null)
    {
        $sql = "SELECT {$fields} FROM `{$table}`";
        $params = [];

        if (!empty($where)) {
            list($whereSql, $params) = $this->buildWhere($where);
            $sql .= " WHERE {$whereSql}";
        }

        if ($order) {
            $sql .= " ORDER BY " . $this->buildOrder($order);
        }

        $sql .= " LIMIT 1";

        $stmt = $this->execute($sql, $params);
        return $stmt->fetch($this->fetchMode);
    }

    /**
     * 获取多条记录
     *
     * @param string $table
     * @param array  $where
     * @param string $fields
     * @param string|array|null $order
     * @param int|null $limit 数量限制
     * @param int $offset 偏移量（与 limit 配合使用）
     * @return array 记录数组（可能为空）
     */
    public function getList(string $table, array $where = [], string $fields = '*', $order = null, ?int $limit = null, int $offset = 0)
    {
        $sql = "SELECT {$fields} FROM `{$table}`";
        $params = [];

        if (!empty($where)) {
            list($whereSql, $params) = $this->buildWhere($where);
            $sql .= " WHERE {$whereSql}";
        }

        if ($order) {
            $sql .= " ORDER BY " . $this->buildOrder($order);
        }

        // 修复：直接拼接整数，避免 PDO 绑定为字符串导致语法错误
        if ($limit !== null) {
            $limit = (int)$limit;
            $offset = (int)$offset;
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll($this->fetchMode);
    }

    /**
     * 分页查询
     *
     * @param string $table
     * @param array  $where
     * @param string $fields 
     * @param string|array|null $order 
     * @param int $page 当前页码，从 1 开始
     * @param int $pageSize 每页条数
     * @return array 包含 list, total, page, pageSize, totalPages 的关联数组
     */
    public function getPage(string $table, array $where = [], string $fields = '*', $order = null, int $page = 1, int $pageSize = 10)
    {
        $total = $this->getCount($table, $where);
        $offset = ($page - 1) * $pageSize;
        $list = $this->getList($table, $where, $fields, $order, $pageSize, $offset);

        return [
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'pageSize'  => $pageSize,
            'totalPages'=> ceil($total / $pageSize),
        ];
    }

    /**
     * 获取满足条件的记录数
     *
     * @param string $table
     * @param array  $where
     * @return int
     */
    public function getCount(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$table}`";
        $params = [];

        if (!empty($where)) {
            list($whereSql, $params) = $this->buildWhere($where);
            $sql .= " WHERE {$whereSql}";
        }

        $stmt = $this->execute($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 获取单个字段值（例如获取用户名）
     *
     * @param string $table
     * @param string $field 要查询的字段名
     * @param array  $where
     * @return mixed|null 字段值，不存在返回 null
     */
    public function getValue(string $table, string $field, array $where = [])
    {
        $row = $this->getOne($table, $where, $field);
        return $row ? current($row) : null;
    }

    /**
     * 获取一列数据（例如所有用户 ID）
     *
     * @param string $table
     * @param string $field
     * @param array  $where
     * @param string|array|null $order
     * @param int|null $limit
     * @param int $offset
     * @return array 包含该列所有值的索引数组
     */
    public function getColumn(string $table, string $field, array $where = [], $order = null, ?int $limit = null, int $offset = 0)
    {
        $sql = "SELECT {$field} FROM `{$table}`";
        $params = [];

        if (!empty($where)) {
            list($whereSql, $params) = $this->buildWhere($where);
            $sql .= " WHERE {$whereSql}";
        }

        if ($order) {
            $sql .= " ORDER BY " . $this->buildOrder($order);
        }

        // 修复：直接拼接整数，避免 PDO 绑定为字符串导致语法错误
        if ($limit !== null) {
            $limit = (int)$limit;
            $offset = (int)$offset;
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * 执行自定义 SQL 查询（用于复杂场景）
     *
     * @param string $sql 带命名占位符的 SQL 语句
     * @param array  $params 绑定参数
     * @param int|null $fetchMode 可选获取模式，不传则使用类默认模式
     * @return array 查询结果（多条）
     */
    public function query(string $sql, array $params = [], ?int $fetchMode = null): array
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll($fetchMode ?? $this->fetchMode);
    }

    /**
     * 执行 SQL 并返回 PDOStatement
     *
     * @param string $sql
     * @param array  $params
     * @return \PDOStatement
     * @throws \PDOException
     */
    protected function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (!$stmt) {
            throw new \PDOException('PDO prepare failed: ' . implode(' ', $this->pdo->errorInfo()));
        }

        // 绑定参数（支持命名占位符）
        foreach ($params as $key => $value) {
            // 如果 key 是数字，则使用位置占位符；否则是命名占位符
            if (is_int($key)) {
                $stmt->bindValue($key + 1, $value);
            } else {
                $stmt->bindValue($key, $value);
            }
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * 构建 WHERE 子句（条件数组转 SQL 片段和参数）
     *
     * 支持格式：
     * - ['field' => value]          => field = ?
     * - ['field >' => value]        => field > ?
     * - ['field LIKE' => '%xx%']    => field LIKE ?
     * - ['id' => [1,2,3]]           => id IN (?,?,?)
     *
     * 多个条件之间以 AND 连接。
     *
     * @param array $conditions
     * @return array [sql, params]
     */
    protected function buildWhere(array $conditions): array
    {
        $parts = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            // 如果键是数字，表示该条件已包含操作符（如 "age > ?"），直接使用
            // 但为了安全性，我们不支持这种原始形式，必须用键值对
            if (is_int($key)) {
                // 忽略数字键（或抛出异常），这里为了健壮，跳过
                continue;
            }

            // 解析字段名和操作符
            $key = trim($key);
            $operator = '=';
            // 如果键包含空格，则分割为字段和操作符
            if (strpos($key, ' ') !== false) {
                list($field, $operator) = preg_split('/\s+/', $key, 2);
                $field = trim($field);
                $operator = trim($operator);
            } else {
                $field = $key;
            }

            // 处理值
            if (is_array($value)) {
                // 数组值：IN 查询
                $placeholders = [];
                foreach ($value as $k => $v) {
                    $paramKey = ":{$field}_{$k}"; // 避免冲突
                    $placeholders[] = $paramKey;
                    $params[$paramKey] = $v;
                }
                $parts[] = "`{$field}` IN (" . implode(',', $placeholders) . ")";
            } else {
                // 标量值：普通比较
                $paramKey = ":{$field}";
                // 防止重复参数名（但这里每个字段只会出现一次，所以安全）
                $parts[] = "`{$field}` {$operator} {$paramKey}";
                $params[$paramKey] = $value;
            }
        }

        $whereSql = implode(' AND ', $parts);
        return [$whereSql, $params];
    }

    /**
     * 构建 ORDER BY 子句
     *
     * @param string|array $order 如 'id DESC' 或 ['id DESC', 'name ASC']
     * @return string
     */
    protected function buildOrder($order): string
    {
        if (is_array($order)) {
            return implode(', ', $order);
        }
        return (string) $order;
    }
}