-- 订单支付记录表
CREATE TABLE `order_payment` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '支付记录编号（唯一）',
  `order_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单编号（关联order_main.number）',
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `payment_method` TINYINT NOT NULL DEFAULT 0 COMMENT '支付方式 0.现金 1.微信 2.支付宝 3.银行转账 4.其他',
  `payment_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '支付时间',
  `operator` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '操作人',
  `remark` VARCHAR(150) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_payment_time` (`payment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单支付记录表';

-- 自动生成支付记录编号的触发器
DELIMITER $$
CREATE TRIGGER `trg_order_payment_number` BEFORE INSERT ON `order_payment`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
