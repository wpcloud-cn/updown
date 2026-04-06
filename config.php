<?php
// config.php - 配置文件
// 管理员密码            ↓
$admin_password = 'admin123';

// 数据文件路径
$data_file = __DIR__ . '/data.json';

// 默认设置
$default_settings = [
    'timeout' => 5,                 // 检测超时时间（秒）
    'auto_refresh_interval' => 300, // 自动刷新间隔（秒），0表示禁用
    'auto_refresh_enabled' => true, // 是否启用自动刷新
    'last_full_check' => null       // 最后一次完整检测时间
];

// 默认示例URL（首次运行时添加）
$default_urls = [
    [
        'id' => 1,
        'url' => 'https://www.google.com',
        'description' => 'Google 搜索',
        'last_status' => null,
        'last_response_time' => null,
        'last_checked' => null,
        'success' => null
    ],
    [
        'id' => 2,
        'url' => 'https://www.github.com',
        'description' => 'GitHub 代码托管',
        'last_status' => null,
        'last_response_time' => null,
        'last_checked' => null,
        'success' => null
    ]
];