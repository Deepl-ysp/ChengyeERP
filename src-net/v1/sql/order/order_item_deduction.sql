-- 订单商品扣减明细表
-- 一条订单商品明细（order_item）可能由多个同名同规格的不同产品凑出来
-- 例如：订了100个螺丝，A产品只有60个，B产品有50个，则从A扣60，从B扣40
CREATE TABLE `order_item_deduction` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `order_item_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单明细ID（关联order_item.id）',
  `order_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '订单编号（冗余，便于查询）',
  `product_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '产品编号（实际扣减的产品，关联supplier_quote.number）',
  `warehouse_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '仓库编号（从哪个仓库扣的，关联warehouse.number）',
  `deducted_quantity` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '扣减数量',
  `unit_cost` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '扣减时成本单价（可选，用于成本核算）',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_order_item_id` (`order_item_id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_product_number` (`product_number`),
  KEY `idx_warehouse_number` (`warehouse_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单商品扣减明细表';
