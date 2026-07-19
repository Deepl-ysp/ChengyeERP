CREATE TABLE `supplier_quote` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '唯一编号（自动生成：时间戳+4位随机数）',
  `name` VARCHAR(20) NOT NULL DEFAULT '未设置' COMMENT '名称',
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `unit` VARCHAR(5) NOT NULL DEFAULT '件' COMMENT '单位',
  `specification` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '规格',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商报价表';

DELIMITER $$
CREATE TRIGGER `trg_supplier_quote_number` BEFORE INSERT ON `supplier_quote`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER;