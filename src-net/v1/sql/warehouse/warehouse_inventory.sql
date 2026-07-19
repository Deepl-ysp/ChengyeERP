-- 仓库库存表（按仓库 + 产品编号汇总当前库存）
CREATE TABLE `warehouse_inventory` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '唯一编号（自动生成：时间戳+4位随机数）',
  `warehouse_number` BIGINT UNSIGNED NOT NULL COMMENT '仓库编号（关联warehouse.number）',
  `product_number` BIGINT UNSIGNED NOT NULL COMMENT '产品编号（关联supplier_quote.number，无外键，应用层逻辑关联）',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前库存数量',
  `min_stock` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '安全库存下限（预警）',
  `max_stock` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '安全库存上限',
  `last_update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间（自动刷新）',
  `remark` VARCHAR(150) NOT NULL DEFAULT '未备注' COMMENT '备注',
  `creata_time` DATE NOT NULL DEFAULT (CURRENT_DATE) COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_warehouse` (`warehouse_number`),
  KEY `idx_product` (`product_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库库存表';

DELIMITER $$
CREATE TRIGGER `trg_warehouse_inventory_number` BEFORE INSERT ON `warehouse_inventory`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;