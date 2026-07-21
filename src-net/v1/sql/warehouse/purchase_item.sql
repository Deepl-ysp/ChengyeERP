-- 采购单明细表
CREATE TABLE `purchase_item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `purchase_number` BIGINT UNSIGNED NOT NULL COMMENT '采购单编号',
  `product_number` BIGINT UNSIGNED NOT NULL COMMENT '产品编号',
  `product_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '产品名称',
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '采购单价',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '采购数量',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '小计金额',
  `warehouse_number` BIGINT UNSIGNED NOT NULL COMMENT '入库仓库编号',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '入库状态：0-待入库 1-部分入库 2-已完成',
  `remark` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `idx_purchase` (`purchase_number`),
  KEY `idx_product` (`product_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='采购单明细表';
