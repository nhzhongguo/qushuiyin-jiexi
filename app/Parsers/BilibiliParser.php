<?php

declare(strict_types=1);

namespace App\Parsers;

use App\Utils\HttpClient;
use App\Utils\UserAgent;

/**
 * Bilibili 视频解析器
 */
class BilibiliParser extends BaseParser
{
    protected static function getHeaders(): array
    {
        return [
            "User-Agent" => UserAgent::desktop(),
            "Referer" => "https://www.bilibili.com/",
        ];
    }

    public static function parse(string $url): array
    {
        // 解析 BV 号或 av 号
        $bvid = null;
        $avid = null;

        $location = HttpClient::getLocation($url);
        $target = $location ?: $url;

        if (preg_match('/BV\w+/i', $target, $match)) {
            $bvid = $match[0];
        } elseif (preg_match('/av(\d+)/i', $target, $match)) {
            $avid = (int) $match[1];
        } else {
            throw new \InvalidArgumentException("无法解析 B 站视频 ID");
        }

        // 通过 B站 API 获取视频信息
        $apiUrl = $bvid
            ? "https://api.bilibili.com/x/web-interface/view?bvid={$bvid}"
            : "https://api.bilibili.com/x/web-interface/view?aid={$avid}";

        $result = self::fetch($apiUrl);

        if (!$result) {
            throw new \RuntimeException("解析视频信息失败");
        }

        $data = self::parseJson($result["data"]);
        $item = $data["data"] ?? null;
        if (!$item) {
            throw new \RuntimeException("视频数据解析失败");
        }

        // 获取视频播放地址
        $cid = $item["cid"] ?? 0;
        $bvid = $item["bvid"] ?? $bvid;
        $avid = $item["aid"] ?? $avid;

        // 取最高画质视频流
        $playResult = self::fetch(
            "https://api.bilibili.com/x/player/playurl?bvid={$bvid}&cid={$cid}&qn=80&fnver=0&fnval=4048&fourk=1"
        );

        $videoUrl = "";
        if ($playResult) {
            $playData = self::parseJson($playResult["data"]);
            $dash = $playData["data"]["dash"] ?? null;
            $durl = $playData["data"]["durl"] ?? null;

            if ($durl && isset($durl[0]["url"])) {
                $videoUrl = $durl[0]["url"];
            } elseif ($dash && isset($dash["video"][0]["baseUrl"])) {
                $videoUrl = $dash["video"][0]["baseUrl"];
            } elseif ($dash && isset($dash["video"][0]["backupUrl"][0])) {
                $videoUrl = $dash["video"][0]["backupUrl"][0];
            }
        }

        if (!$videoUrl) {
            throw new \RuntimeException("未找到视频 URL");
        }

        return [
            "author" => $item["owner"]["name"] ?? "",
            "uid" => $item["owner"]["mid"] ?? "",
            "avatar" => $item["owner"]["face"] ?? "",
            "like" => $item["stat"]["like"] ?? 0,
            "time" => $item["pubdate"] ?? 0,
            "title" => $item["title"] ?? "",
            "cover" => $item["pic"] ?? "",
            "url" => $videoUrl,
        ];
    }
}
