-- 资金流水表（统一记录所有收支：客户收款、供应商付款、其他收支）
CREATE TABLE `finance_flow` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '流水编号（唯一）',
  `flow_type` TINYINT NOT NULL DEFAULT 0 COMMENT '流水类型 0.客户收款 1.供应商付款 2.其他收入 3.其他支出',
  `direction` TINYINT NOT NULL DEFAULT 0 COMMENT '收支方向 0.收入 1.支出',
  `party_type` TINYINT NOT NULL DEFAULT 0 COMMENT '对方类型 0.客户 1.供应商 2.其他',
  `party_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对方编号（客户/供应商）',
  `party_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '对方名称',
  `related_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联单据号（订单号/采购单号）',
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `payment_method` TINYINT NOT NULL DEFAULT 0 COMMENT '支付方式 0.现金 1.微信 2.支付宝 3.银行转账 4.其他',
  `flow_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '收支时间',
  `operator` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '操作人',
  `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注',
  `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT '是否删除 0.否 1.是',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_flow_type` (`flow_type`),
  KEY `idx_direction` (`direction`),
  KEY `idx_party` (`party_type`, `party_number`),
  KEY `idx_related` (`related_number`),
  KEY `idx_flow_time` (`flow_time`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资金流水表';

-- 自动生成流水编号
DELIMITER $$
CREATE TRIGGER `trg_finance_flow_number` BEFORE INSERT ON `finance_flow`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
