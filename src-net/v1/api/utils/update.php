<?php
// utils/update.php

/**
 * 通用 MySQL 更新类（仅负责 UPDATE 操作）
 * 与 DbGet、DbSet 完全独立，零耦合
 */
class DbUpdate
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
     * 更新记录（支持条件）
     *
     * @param string $table  表名
     * @param array  $data   要更新的数据（关联数组，键为字段名）
     * @param array  $where  条件数组（格式与 DbGet 的 where 一致）
     * @return int 受影响的行数
     * @throws \PDOException
     */
    public function update(string $table, array $data, array $where): int
    {
        // 构建 SET 子句
        $setParts = [];
        $params = [];
        foreach ($data as $field => $value) {
            $paramKey = ":set_{$field}";
            $setParts[] = "`{$field}` = {$paramKey}";
            $params[$paramKey] = $value;
        }

        // 构建 WHERE 子句
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
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * 构建 WHERE 子句（与 DbGet 完全相同的逻辑，但独立复制确保零耦合）
     *
     * @param array $conditions
     * @return array [sql, params]
     */
    protected function buildWhere(array $conditions): array
    {
        $parts = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                continue;
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