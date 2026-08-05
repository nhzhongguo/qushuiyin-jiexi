<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * 西瓜视频解析器
 */
class XiguaParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::desktop(),
            "Referer" => "https://www.ixigua.com/",
        ];
    }

    public static function parse(string $url): array
    {
        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        // 提取 video_id：优先匹配 /video/xxx 或 /item/xxx 路径中的数字
        if (!preg_match('#/(?:video|item)/(\d+)#i', $target, $match)) {
            // 兼容仅含纯数字 ID 的短链/直链
            if (!preg_match('/\b(\d{10,25})\b/', $target, $match)) {
                throw new \InvalidArgumentException("无法解析西瓜视频 ID");
            }
        }

        $videoId = $match[1];

        // 通过西瓜视频/今日头条 API 获取信息
        $result = self::fetch(
            "https://www.ixigua.com/api/video/info?vid={$videoId}"
        );

        if (!$result) {
            throw new \RuntimeException("解析视频信息失败");
        }

        $data = self::parseJson($result["data"]);
        $item = $data["data"]["video_info"] ?? null;

        if (!$item) {
            // 尝试另一种 API
            $result2 = self::fetch(
                "https://www.ixigua.com/api/video/info?video_id={$videoId}"
            );
            if ($result2) {
                $data2 = self::parseJson($result2["data"]);
                $item = $data2["data"]["video_info"] ?? $data2["data"] ?? null;
            }
        }

        if (!$item) {
            throw new \RuntimeException("视频数据解析失败");
        }

        $videoUrl = "";
        $videoList = $item["video"]["video_list"] ?? [];
        // 取最高画质（最后一个通常是最高清）
        foreach (["video_list_4k", "video_list_3", "video_list_2", "video_list_1", "video_list_0"] as $key) {
            if (isset($videoList[$key]["main_url"])) {
                $videoUrl = $videoList[$key]["main_url"];
                break;
            }
        }

        if (!$videoUrl && !empty($videoList)) {
            $keys = array_keys($videoList);
            $lastKey = end($keys);
            $videoUrl = $videoList[$lastKey]["main_url"] ?? "";
        }

        if (!$videoUrl) {
            throw new \RuntimeException("未找到视频 URL");
        }

        // main_url 是 base64 编码的
        $decodedUrl = base64_decode($videoUrl);
        if ($decodedUrl === false || $decodedUrl === "") {
            $decodedUrl = $videoUrl;
        }

        $user = $item["user_info"] ?? $item["author"] ?? [];

        return [
            "author" => $user["name"] ?? $user["screen_name"] ?? "",
            "uid" => $user["user_id"] ?? $user["id"] ?? "",
            "avatar" => $user["avatar_url"] ?? "",
            "like" => $item["statistics"]["digg_count"] ?? $item["digg_count"] ?? 0,
            "time" => $item["create_time"] ?? $item["createTime"] ?? 0,
            "title" => $item["title"] ?? $item["video"]["title"] ?? "",
            "cover" => $item["video"]["poster_url"] ?? $item["cover_url"] ?? "",
            "url" => self::fixUrl($decodedUrl),
        ];
    }
}

