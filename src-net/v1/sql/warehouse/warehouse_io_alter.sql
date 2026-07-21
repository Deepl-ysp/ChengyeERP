-- 修改warehouse_io表，增加财务相关字段
ALTER TABLE `warehouse_io` 
ADD COLUMN `is_finance` TINYINT NOT NULL DEFAULT 0 COMMENT '是否计入财务系统：0-否 1-是' AFTER `remark`,
ADD COLUMN `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '单价（用于财务计算）' AFTER `quantity`,
ADD COLUMN `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '总金额（quantity * unit_price）' AFTER `unit_price`;

-- 添加索引
ALTER TABLE `warehouse_io` ADD KEY `idx_is_finance` (`is_finance`);
