-- 客户表
CREATE TABLE `customer` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增主键',
  `number` BIGINT UNSIGNED NOT NULL COMMENT '唯一编号（自动生成：时间戳+4位随机数）',
  `name` VARCHAR(20) NOT NULL DEFAULT '未设置' COMMENT '客户名称',
  `contact` VARCHAR(10) NOT NULL DEFAULT '未知联系人' COMMENT '联系人',
  `phone_number` VARCHAR(12) NOT NULL DEFAULT ' ' COMMENT '手机号（默认空格）',
  `email` VARCHAR(25) NOT NULL DEFAULT 'exp@exp.com' COMMENT '邮箱',
  `address` VARCHAR(80) NOT NULL DEFAULT '未知地址' COMMENT '地址',
  `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态 0.启用 1.禁用 2.异常',
  `remark` VARCHAR(150) NOT NULL DEFAULT '未备注' COMMENT '备注',
  `creata_time` DATE NOT NULL DEFAULT (CURRENT_DATE) COMMENT '创建时间', -- 沿用原表字段名风格（含拼写）
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_number` (`number`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客户表';

-- 自动生成编号的触发器（插入前若 number 为空或0则自动生成）
DELIMITER $$
CREATE TRIGGER `trg_customer_number` BEFORE INSERT ON `customer`
FOR EACH ROW
BEGIN
  IF NEW.`number` IS NULL OR NEW.`number` = 0 THEN
    SET NEW.`number` = UNIX_TIMESTAMP() * 10000 + FLOOR(RAND() * 10000);
  END IF;
END$$
DELIMITER ;