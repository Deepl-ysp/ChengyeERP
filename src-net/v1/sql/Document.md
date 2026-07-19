# 供应商主表
- id 自增 主键 索引 uint 自增无需默认值
- number 唯一key 唯一编号 索引 uint 无值默认为当前时间戳+4位随机数
- name 名称 索引 varchar[20] 默认 未设置
- email 邮箱 varchar[25] 默认 exp@exp.com
- address 地址 varchar[80] 默认 未知地址
- contact 联系人 varchar[10] 默认 未知联系人
- phone_number 手机号 varchar[12] 默认 " "
- status 状态 tyint 0.启用 1.禁用 2.异常 默认0
- remark 备注 varchar[150] 默认 未备注
- creata_time 创建时间 date 以创建时间位值

# 供应商报价表
- id 自增 主键 索引 uint 自增无需默认值
- number 唯一key 唯一编号 索引 uint 无值默认为当前时间戳+4位随机数
- name 名称 索引 varchar[20] 默认 未设置
- unit_price 单价 decimal[10, 2] 默认0.00
- unit 单位 varchar[5] 默认 件
- specification 规格 uint 默认 0
