<?php
// config/order.config.php
// 订单图片保存配置

return [
    // 图片保存根目录（绝对路径）
    'image_base_path' => 'C:\\订单',

    // 日期子目录格式（如 2026.07.20）
    'date_format' => 'Y.m.d',

    // 文件名格式：日期-订单号.png（不含"销货清单"字样）
    'filename_format' => '{date}-{order_number}.png',

    // 图片允许的最大 base64 数据大小（字节，默认 10MB）
    'max_base64_size' => 104857600,
];
