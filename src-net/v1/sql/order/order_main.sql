-- 订单主表
CREATE TABLE `order_main` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '订单编号（唯一）',
  `customer_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '客户单位',
  `customer_number` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客户编号（关联customer表）',
  `seller_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收款单位',
  `receiver_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '收货单位',
  `receiver_address` VARCHAR(150) NOT NULL DEFAULT '' COMMENT '收货地址',
  `receiver_phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '联系电话',
  `total_quantity` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '商品总数',
  `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
  `paid_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT '已付金额',
  `payment_status` TINYINT NOT NULL DEFAULT 0 COMMENT '支付状态 0.未支付 1.部分支付 2.全额支付',
  `order_status` TINYINT NOT NULL DEFAULT 0 COMMENT '订单状态 0.待处理 1.已确认 2.已发货 3.已完成 4.已取消',
  `remark` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '备注',
  `image_path` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单图片路径',
  `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT '是否删除 0.未删除 1.已删除',
  `create_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_customer_number` (`customer_number`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_order_status` (`order_status`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_is_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单主表';

-- 自动生成订单编号的触发器
DELIMITER $$
CREATE TRIGGER `trg_order_main_number` BEFORE INSERT ON `order_main`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;
