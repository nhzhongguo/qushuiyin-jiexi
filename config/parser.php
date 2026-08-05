<?php

use App\Utils\Config;

// 确保环境变量已加载
Config::env('APP_NAME');

/**
 * 解析器配置文件
 */
return [
    // 抖音解析器配置
    'douyin' => [
        'node_bin' => Config::env('DOUYIN_NODE_BIN', 'node'),
        'a_bogus_script' => Config::env('DOUYIN_A_BOGUS_SCRIPT', dirname(__DIR__, 2) . '/scripts/a_bogus.js'),
        'a_bogus_port' => (int)Config::env('A_BOGUS_PORT', '9876'),
        'ttwid_url' => 'https://ttwid.bytedance.com/ttwid/union/register/',
        'detail_url' => 'https://www.douyin.com/aweme/v1/web/aweme/detail/',
        'legacy_url' => 'https://www.iesdouyin.com/web/api/v2/aweme/iteminfo/',
    ],

    // 快手解析器配置
    'kuaishou' => [
        'api_url' => 'https://www.kuaishou.com/graphql',
        'legacy_url' => 'https://www.kuaishou.com/api/v2/author/video',
    ],

    // B站解析器配置
    'bilibili' => [
        'api_url' => 'https://api.bilibili.com/x/web-interface/view',
        'player_url' => 'https://api.bilibili.com/x/player/playurl',
    ],

    // 小红书解析器配置
    'xiaohongshu' => [
        'api_url' => 'https://www.xiaohongshu.com/api/sns/v1/note/',
        'search_url' => 'https://www.xiaohongshu.com/api/sns/v1/search/notes',
    ],

    // 视频号解析器配置
    'shipinhao' => [
        'api_url' => 'https://channels.weixin.qq.com/cgi-bin/mmfinder/finder_live/get_finder_live_info',
        'video_url' => 'https://channels.weixin.qq.com/cgi-bin/mmfinder/finder_live/get_finder_video_info',
    ],

    // 西瓜视频解析器配置
    'xigua' => [
        'api_url' => 'https://www.ixigua.com/api/video/detail/',
        'play_url' => 'https://www.ixigua.com/api/video/playinfo/',
    ],

    // 皮皮虾解析器配置
    'pipixia' => [
        'api_url' => 'https://h5.pipix.com/bds/webapi/item/detail/',
    ],

    // 微博解析器配置
    'weibo' => [
        'cookie' => Config::env('WEIBO_COOKIE', ''),
        'api_url' => 'https://weibo.com/ajax/statuses/show?id=',
    ],

    // 微视解析器配置
    'weishi' => [
        'api_url' => 'https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage',
    ],

    // 最右解析器配置
    'izuiyou' => [
        'api_url' => 'https://izuiyou.com/api/content/content_detail',
    ],

    // 皮皮搞笑解析器配置
    'pipigx' => [
        'api_url' => 'https://share.ippzone.com/ppapi/share/fetch_content',
    ],
];