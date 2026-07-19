<?php
// config/image.config.php

return [
    // ---------- 图片整体 ----------
    'width' => 800,          // 图片宽度（像素）
    'bg_color' => [255, 255, 255], // 背景色 RGB
    'padding' => 30,           // 内边距
    'line_height' => 28,           // 行高
    'font_file' => __DIR__ . '/../fonts/hei.ttf', // 中文字体路径

    // ---------- 标题 ----------
    'title' => '销货清单',
    'title_font_size' => 24,
    'title_color' => [0, 0, 0],

    // ---------- 头部信息（客户、日期等） ----------
    'info_font_size' => 14,
    'info_color' => [0, 0, 0],

    // ---------- 表格 ----------
    'table_header_height' => 30,
    'table_row_height' => 26,
    'table_font_size' => 13,
    'table_header_color' => [255, 255, 255],
    'table_header_bg' => [70, 130, 180], // 表头背景色
    'table_border_color' => [0, 0, 0],
    'table_text_color' => [0, 0, 0],
    'table_col_widths' => [50, 220, 70, 70, 70, 100], // 序号, 商品名称, 单位, 数量, 单价, 金额小计

    // ---------- 底部合计 ----------
    'total_font_size' => 15,
    'total_color' => [200, 0, 0],

    // ---------- 水印 ----------
    'watermark' => [
        'text' => '义乌市越貌涂料商行',      // 水印文字内容
        'font_size' => 16,
        'color' => [0, 0, 0, 100], // RGBA，最后一个为透明度 (0-127)
        'angle' => 45,                  // 倾斜角度
        'position' => 'bottom_right',      // 位置：bottom_right, bottom_left, top_right, top_left, center
        'spacing_x' => 300,           // 水平间距（像素）
        'spacing_y' => 120,            // 垂直间距（像素）
        'enabled' => true,                // 是否启用
    ],

    // ---------- 图片保存 ----------
    'save_format' => 'png',           // png 或 jpg
    'quality' => 90,              // jpg 品质 (0-100)
    'base_path' => __DIR__ . '/../top', // 图片存储根目录
    'date_format' => 'Y.n.j',         // 子目录格式（如 2026.7.8）
];