-- 客户应收对账单（按客户+时间段统计对账）
CREATE TABLE `finance_receivable` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '对账单编号（唯一）',
  `period_type` TINYINT NOT NULL DEFAULT 0 COMMENT '周期类型 0.日 1.周 2.月 3.自定义',
  `start_date` DATE NOT NULL COMMENT '开始日期',
  `end_date` DATE NOT NULL COMMENT '结束日期',
  `customer_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户编号（0=全部客户汇总）',
  `customer_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '客户名称',
  `order_count` INT NOT NULL DEFAULT 0 COMMENT '订单数',
  `order_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '订单总额',
  `received_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '已收金额',
  `unreceived_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '未收金额',
  `is_balanced` TINYINT NOT NULL DEFAULT 0 COMMENT '是否对账 0.未对账 1.已对账',
  `reconciler` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '对账人',
  `reconcile_time` DATETIME DEFAULT NULL COMMENT '对账时间',
  `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_customer_number` (`customer_number`),
  KEY `idx_period` (`start_date`, `end_date`),
  KEY `idx_period_type` (`period_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户应收对账单';

-- 自动生成对账单编号
DELIMITER $$
CREATE TRIGGER `trg_finance_receivable_number` BEFORE INSERT ON `finance_receivable`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
