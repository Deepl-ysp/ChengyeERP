-- 订单商品明细表
-- 注意：一条订单项可能由多个同名同规格的不同产品凑出来（A产品货不够扣B产品）
-- product_number 仅记录主产品编号（第一个被扣减的），详细扣减记录见 order_item_deduction
CREATE TABLE `order_item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `order_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单编号（关联order_main.number）',
  `product_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '商品名称',
  `product_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '主产品编号（第一个被扣减的产品，详细扣减见order_item_deduction）',
  `unit` VARCHAR(10) NOT NULL DEFAULT '件' COMMENT '单位',
  `quantity` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '数量',
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '单价',
  `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '金额小计',
  `specification` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '规格',
  `warehouse_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '出货仓库编号（主要出货仓库，多仓库扣减时记录第一个）',
  `remark` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_product_number` (`product_number`),
  KEY `idx_product_name` (`product_name`),
  KEY `idx_warehouse_number` (`warehouse_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品明细表';
