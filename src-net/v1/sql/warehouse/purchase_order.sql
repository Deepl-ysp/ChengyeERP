-- 采购单主表
CREATE TABLE `purchase_order` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '采购单编号（自动生成）',
  `supplier_number` BIGINT UNSIGNED NOT NULL COMMENT '供应商编号',
  `supplier_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '供应商名称',
  `warehouse_number` BIGINT UNSIGNED NOT NULL COMMENT '入库仓库编号',
  `warehouse_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '仓库名称',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '采购总额',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态：0-待确认 1-已确认 2-已入库 3-已完成',
  `operator` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '操作人',
  `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注',
  `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT '是否删除',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `confirm_time` DATETIME DEFAULT NULL COMMENT '确认时间',
  `complete_time` DATETIME DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_supplier` (`supplier_number`),
  KEY `idx_warehouse` (`warehouse_number`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购单主表';

-- 自动生成采购单编号
DELIMITER $$
CREATE TRIGGER `trg_purchase_order_number` BEFORE INSERT ON `purchase_order`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
