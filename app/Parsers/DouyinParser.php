<?php

namespace App\Parsers;

use App\Contracts\AbstractParser;
use App\Utils\Config;
use App\Utils\HttpClient;
use App\Utils\Logger;
use App\Utils\UserAgent;

/**
 * 抖音视频解析器
 */
class DouyinParser extends AbstractParser
{
    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

    public static function getHeaders(): array
    {
        return ['User-Agent' => UserAgent::mobile()];
    }

    public static function parse(string $url): array
    {
        $videoId = self::extractVideoId($url);
        if ($videoId === null) {
            throw new \InvalidArgumentException('无法解析视频 ID');
        }

        $item = null;
        $error = '';

        try {
            $item = self::fetchAwemeDetail($videoId);
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }

        if ($item === null) {
            $item = self::fetchLegacy($videoId);
        }

        if ($item === null) {
            throw new \RuntimeException($error !== '' ? $error : '解析视频信息失败，视频可能已删除或接口暂时失效');
        }

        return self::buildResult($item);
    }

    protected static function extractVideoId(string $url): ?string
    {
        if (preg_match('#/(?:video|note|share/video)/(\d+)#i', $url, $match)) {
            return $match[1];
        }

        if (preg_match('#https?://v\.douyin\.com/#i', $url)) {
            $location = HttpClient::getLocation($url);
            if ($location && preg_match('#/(?:video|note|share/video)/(\d+)#i', $location, $match)) {
                return $match[1];
            }

            $result = self::fetch($url);
            if ($result && preg_match('#/(?:video|note|share/video)/(\d+)#i', $result['data'], $match)) {
                return $match[1];
            }
        }

        if (preg_match('/\b\d{15,25}\b/', $url, $match)) {
            return $match[0];
        }

        return null;
    }

    private static function fetchAwemeDetail(string $videoId): ?array
    {
        $ua = self::DESKTOP_UA;
        $ttwid = self::getTtwid($ua);
        if ($ttwid === null) {
            Logger::error('获取抖音 ttwid 失败', ['video_id' => $videoId]);
            return null;
        }

        $msToken = self::randomToken(107);
        $query = http_build_query([
            'device_platform' => 'webapp',
            'aid' => '6383',
            'channel' => 'channel_pc_web',
            'aweme_id' => $videoId,
            'msToken' => $msToken,
        ]);

        $aBogus = self::generateABogus($query, $ua);
        if ($aBogus === '') {
            throw new \RuntimeException('抖音签名生成失败，请确认服务器已安装 Node.js 18+ 并配置 DOUYIN_NODE_BIN');
        }

        $url = 'https://www.douyin.com/aweme/v1/web/aweme/detail/?' . $query . '&a_bogus=' . rawurlencode($aBogus);
        $headers = [
            'User-Agent' => $ua,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'Referer' => 'https://www.douyin.com/video/' . $videoId,
            'Cookie' => 'ttwid=' . $ttwid,
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
        ];

        $rawHeaders = '';
        $result = HttpClient::request($url, null, $headers, null, $rawHeaders);
        if (!$result['success']) {
            Logger::error('抖音详情接口请求失败', [
                'video_id' => $videoId,
                'error' => $result['error'],
                'http_code' => $result['http_code'],
            ]);
            return null;
        }

        $json = self::parseJson($result['data']);
        if (!is_array($json) || !isset($json['aweme_detail']) || !is_array($json['aweme_detail'])) {
            Logger::error('抖音详情接口未返回数据', [
                'video_id' => $videoId,
                'body' => mb_substr($result['data'], 0, 500),
            ]);
            return null;
        }

        return $json['aweme_detail'];
    }

    private static function getTtwid(string $ua): ?string
    {
        $payload = json_encode([
            'region' => 'cn',
            'aid' => 6383,
            'need_t' => 1,
            'service' => 'www.douyin.com',
            'migrate_priority' => 0,
            'cb_url_protocol' => 'https',
            'domain' => '.douyin.com',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $rawHeaders = '';
        $result = HttpClient::request(
            'https://ttwid.bytedance.com/ttwid/union/register/',
            $payload,
            [
                'User-Agent' => $ua,
                'Content-Type' => 'application/json',
            ],
            null,
            $rawHeaders
        );

        if (!$result['success']) {
            return null;
        }

        if (preg_match('/Set-Cookie:\s*ttwid=([^;\s]+)/i', $rawHeaders, $match)) {
            return urldecode($match[1]);
        }

        return null;
    }

    private static function generateABogus(string $query, string $ua): string
    {
        // Try persistent signing server first (much faster)
        $serverPort = Config::env('A_BOGUS_PORT', '9876');
        $serverHost = '127.0.0.1';

        $startTime = microtime(true);

        // Try HTTP signing server with short timeout
        $ch = curl_init("http://{$serverHost}:{$serverPort}/");
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['query' => $query, 'ua' => $ua]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($output !== false && $httpCode === 200 && trim($output) !== '') {
                Logger::error('a_bogus 签名服务耗时', ['ms' => round((microtime(true) - $startTime) * 1000)]);
                return trim($output);
            }
        }

        // Fallback to proc_open
        $nodeBin = Config::env('DOUYIN_NODE_BIN', 'node');
        $script = Config::env('DOUYIN_A_BOGUS_SCRIPT', dirname(__DIR__, 2) . '/scripts/a_bogus.js');

        $command = sprintf(
            '%s %s --query %s --ua %s',
            escapeshellarg($nodeBin),
            escapeshellarg($script),
            escapeshellarg($query),
            escapeshellarg($ua)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('无法启动 Node.js 生成抖音签名');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $output === false || trim($output) === '') {
            Logger::error('a_bogus 生成失败', ['exit_code' => $exitCode, 'error' => $error]);
            return '';
        }

        return trim($output);
    }

    private static function randomToken(int $length): string
    {
        $chars = 'ABCDEFGHIGKLMNOPQRSTUVWXYZabcdefghigklmnopqrstuvwxyz0123456789=';
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }

    private static function fetchLegacy(string $videoId): ?array
    {
        $result = self::fetch("https://www.iesdouyin.com/share/video/{$videoId}");
        if (!$result || !preg_match('/window\._ROUTER_DATA\s*=\s*(.*?)\<\/script>/s', $result['data'], $matches)) {
            return null;
        }

        $data = self::parseJson(trim($matches[1]));

        return $data['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0] ?? null;
    }

    protected static function buildResult(array $item): array
    {
        return [
            'author' => $item['author']['nickname'] ?? '',
            'uid' => $item['author']['uid'] ?? $item['author']['unique_id'] ?? '',
            'avatar' => self::firstUrl($item['author']['avatar_medium']['url_list'] ?? null)
                ?? self::firstUrl($item['author']['avatar_thumb']['url_list'] ?? null)
                ?? self::firstUrl($item['author']['avatar_larger']['url_list'] ?? null)
                ?? '',
            'like' => $item['statistics']['digg_count'] ?? 0,
            'time' => $item['create_time'] ?? 0,
            'title' => $item['desc'] ?? '',
            'cover' => self::firstUrl($item['video']['cover']['url_list'] ?? null)
                ?? self::firstUrl($item['video']['origin_cover']['url_list'] ?? null)
                ?? '',
            'url' => self::pickVideoUrl($item),
            'music' => [
                'author' => $item['music']['author'] ?? '',
                'avatar' => self::firstUrl($item['music']['cover_large']['url_list'] ?? null)
                    ?? self::firstUrl($item['music']['cover_thumb']['url_list'] ?? null)
                    ?? '',
            ],
        ];
    }

    private static function pickVideoUrl(array $item): string
    {
        $candidates = [];

        $playUrls = $item['video']['play_addr']['url_list'] ?? [];
        if (is_array($playUrls) && isset($playUrls[0])) {
            $candidates[] = $playUrls[0];
        }

        $bitRates = $item['video']['bit_rate'] ?? [];
        if (is_array($bitRates)) {
            usort($bitRates, static fn(array $a, array $b): int => (int) ($b['bit_rate'] ?? 0) <=> (int) ($a['bit_rate'] ?? 0));
            foreach ($bitRates as $bitRate) {
                $urls = $bitRate['play_addr']['url_list'] ?? [];
                if (is_array($urls) && isset($urls[0])) {
                    $candidates[] = $urls[0];
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return self::fixUrl($candidate);
            }
        }

        $uri = $item['video']['play_addr']['uri'] ?? '';
        if ($uri !== '') {
            return "https://www.douyin.com/aweme/v1/play/?video_id={$uri}&ratio=1080p&line=0";
        }

        throw new \RuntimeException('未找到视频 URL');
    }

    private static function firstUrl($urlList): ?string
    {
        if (!is_array($urlList) || !isset($urlList[0]) || !is_string($urlList[0]) || $urlList[0] === '') {
            return null;
        }

        return self::fixUrl($urlList[0]);
    }
}
