-- 客户价格管理表
CREATE TABLE `customer_price` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '唯一编号（自动生成：时间戳+4位随机数）',
  `customer_number` BIGINT UNSIGNED NOT NULL COMMENT '客户编号（关联customer表的number）',
  `product_name` VARCHAR(20) NOT NULL DEFAULT '未设置' COMMENT '产品名称',
  `specification` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '规格（字符串，如20KG）',
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `unit` VARCHAR(5) NOT NULL DEFAULT '件' COMMENT '单位',
  `effective_date` DATE NOT NULL DEFAULT (CURRENT_DATE) COMMENT '生效日期',
  `expiry_date` DATE DEFAULT NULL COMMENT '失效日期（NULL表示长期有效）',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态 0.启用 1.禁用',
  `remark` VARCHAR(150) NOT NULL DEFAULT '未备注' COMMENT '备注',
  `creata_time` DATE NOT NULL DEFAULT (CURRENT_DATE) COMMENT '创建时间', -- 沿用原表拼写风格
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_customer_number` (`customer_number`),
  KEY `idx_product_name` (`product_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户价格管理表';

-- 自动生成编号的触发器（插入前若 number 为空或0则自动生成）
DELIMITER $$
CREATE TRIGGER `trg_customer_price_number` BEFORE INSERT ON `customer_price`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;