-- 进出库表（记录每次入库/出库的明细）
CREATE TABLE `warehouse_io` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '唯一编号（自动生成：时间戳+4位随机数）',
  `warehouse_number` BIGINT UNSIGNED NOT NULL COMMENT '仓库编号（关联warehouse.number）',
  `product_number` BIGINT UNSIGNED NOT NULL COMMENT '产品编号（关联supplier_quote.number，无外键，应用层逻辑关联）',
  `direction` TINYINT NOT NULL DEFAULT 1 COMMENT '出入方向 1.入库 0.出库',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '数量（绝对值）',
  `before_quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动前库存',
  `after_quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '变动后库存',
  `related_order_no` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '关联单号（如采购单号/销售单号）',
  `operator` VARCHAR(10) NOT NULL DEFAULT '系统' COMMENT '操作人',
  `remark` VARCHAR(150) NOT NULL DEFAULT '未备注' COMMENT '备注',
  `creata_time` DATE NOT NULL DEFAULT (CURRENT_DATE) COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_warehouse` (`warehouse_number`),
  KEY `idx_product` (`product_number`),
  KEY `idx_direction` (`direction`),
  KEY `idx_creata_time` (`creata_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='进出库表';

DELIMITER $$
CREATE TRIGGER `trg_warehouse_io_number` BEFORE INSERT ON `warehouse_io`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
