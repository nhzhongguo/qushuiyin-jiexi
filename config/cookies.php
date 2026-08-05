<?php

use App\Utils\Config;

// 确保环境变量已加载
Config::env('APP_NAME');

/**
 * Cookie 配置文件
 *
 * 环境变量命名规则：
 * - 微博：WEIBO_COOKIE
 * - 抖音：DOUYIN_COOKIE
 * - 皮皮虾：PIPIXIA_COOKIE
 */
return [
    'weibo' => [
        'cookie' => Config::env('WEIBO_COOKIE', ''),
    ],
];
