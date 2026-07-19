<?php
// utils/set.php

/**
 * 通用 MySQL 写入类（基于 PDO）
 * 提供安全的插入、更新、删除操作
 */
class DbSet
{
    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * 构造函数
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 插入一条记录
     *
     * @param string $table 表名
     * @param array  $data  关联数组，键为字段名，值为数据
     * @return int 最后插入的 ID（若表有自增主键）
     * @throws \PDOException
     */
    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ":" . $field;
        }, $fields);

        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $table,
            implode(', ', array_map(function($f) { return "`$f`"; }, $fields)),
            implode(', ', $placeholders)
        );

        $stmt = $this->execute($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 更新记录（支持条件）
     *
     * @param string $table
     * @param array  $data  要更新的数据（关联数组）
     * @param array  $where 条件数组，与 DbGet 中的 where 格式一致
     * @return int 受影响的行数
     */
    public function update(string $table, array $data, array $where): int
    {
        $setParts = [];
        $params = [];
        foreach ($data as $field => $value) {
            $paramKey = ":set_{$field}";
            $setParts[] = "`{$field}` = {$paramKey}";
            $params[$paramKey] = $value;
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts);
        if (!empty($where)) {
            list($whereSql, $whereParams) = $this->buildWhere($where);
            $sql .= " WHERE {$whereSql}";
            $params = array_merge($params, $whereParams);
        }

        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 删除记录
     *
     * @param string $table
     * @param array  $where 条件数组（必须非空，否则安全保护）
     * @return int 受影响的行数
     * @throws \InvalidArgumentException 如果条件为空
     */
    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new \InvalidArgumentException("DELETE 操作必须提供条件，以防止全表删除");
        }

        list($whereSql, $params) = $this->buildWhere($where);
        $sql = "DELETE FROM `{$table}` WHERE {$whereSql}";
        $stmt = $this->execute($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 执行预处理语句（内部方法）
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

        foreach ($params as $key => $value) {
            // 支持命名占位符，无需指定类型，PDO 会自行处理
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * 构建 WHERE 子句（复用与 DbGet 相同的逻辑）
     *
     * @param array $conditions
     * @return array [sql, params]
     */
    protected function buildWhere(array $conditions): array
    {
        // 此处可以直接复用 DbGet 的 buildWhere 方法，但为保持独立，复制一份
        // 您也可以将 buildWhere 提取到公共辅助类
        $parts = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                continue; // 忽略数字键
            }

            $key = trim($key);
            $operator = '=';
            if (strpos($key, ' ') !== false) {
                list($field, $operator) = preg_split('/\s+/', $key, 2);
                $field = trim($field);
                $operator = trim($operator);
            } else {
                $field = $key;
            }

            if (is_array($value)) {
                $placeholders = [];
                foreach ($value as $k => $v) {
                    $paramKey = ":{$field}_{$k}";
                    $placeholders[] = $paramKey;
                    $params[$paramKey] = $v;
                }
                $parts[] = "`{$field}` IN (" . implode(',', $placeholders) . ")";
            } else {
                $paramKey = ":{$field}";
                $parts[] = "`{$field}` {$operator} {$paramKey}";
                $params[$paramKey] = $value;
            }
        }

        $whereSql = implode(' AND ', $parts);
        return [$whereSql, $params];
    }
}