-- 综合对账记录（按周期汇总收支+对账状态）
CREATE TABLE `finance_reconciliation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '对账编号（唯一）',
  `period_type` TINYINT NOT NULL DEFAULT 0 COMMENT '周期类型 0.日 1.周 2.月 3.自定义',
  `period_label` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '周期标签（如：2026-07-21 / 2026-W29 / 2026-07）',
  `start_time` DATETIME NOT NULL COMMENT '开始时间',
  `end_time` DATETIME NOT NULL COMMENT '结束时间',
  `income_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '收入合计（客户收款）',
  `expense_total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '支出合计（供应商付款+其他支出）',
  `net_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '净收入',
  `order_count` INT NOT NULL DEFAULT 0 COMMENT '订单数',
  `flow_count` INT NOT NULL DEFAULT 0 COMMENT '流水笔数',
  `is_balanced` TINYINT NOT NULL DEFAULT 0 COMMENT '是否对账 0.未对账 1.已对账 2.异常',
  `reconciler` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '对账人',
  `reconcile_time` DATETIME DEFAULT NULL COMMENT '对账时间',
  `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_period_label` (`period_label`),
  KEY `idx_period_type` (`period_type`),
  KEY `idx_time_range` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='综合对账记录';

-- 自动生成对账编号
DELIMITER $$
CREATE TRIGGER `trg_finance_reconciliation_number` BEFORE INSERT ON `finance_reconciliation`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
