<?php
// config/log.config.php

return [
    // 日志根目录（网站根目录下的 /log）
    'log_dir' => __DIR__ . '/../log',

    // 日期格式（用于生成每天一个日志文件）
    'date_format' => 'Y-m-d',

    // 日志级别配置
    'levels' => [
        'debug' => [
            'max_days' => 7,          // 保留最近 7 天的 debug 日志
            'max_lines_per_file' => 10000, // 每个文件最大行数（超过则轮转，但按天的话一般不超）
        ],
        'info' => [
            'max_days' => 30,
            'max_lines_per_file' => 10000,
        ],
        'warning' => [
            'max_days' => 30,
            'max_lines_per_file' => 10000,
        ],
        'error' => [
            'max_days' => 90,
            'max_lines_per_file' => 10000,
        ],
    ],

    // 默认级别（如果未指定）
    'default_level' => 'debug',

    // 文件权限（八进制）
    'file_permission' => 0644,

    // 日志格式（时间戳 + 级别 + 消息）
    'log_format' => "[%s] [%s] %s%s", // 参数：时间, 级别, 消息, 上下文
];