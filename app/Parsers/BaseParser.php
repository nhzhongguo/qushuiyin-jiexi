<?php

namespace App\Parsers;

use App\Utils\HttpClient;

/**
 * 视频解析器抽象基类
 */
abstract class BaseParser
{
    /**
     * 获取 HTTP 请求头
     * 
     * @return array HTTP 请求头数组，格式为 ['Header' => 'value']
     */
    abstract protected static function getHeaders(): array;
    
    /**
     * 解析视频 URL
     * 
     * @param string $url 视频 URL
     * @return array 解析结果数据数组
     * @throws \InvalidArgumentException 参数错误时抛出
     * @throws \RuntimeException 解析失败时抛出
     */
    abstract public static function parse(string $url): array;

    /**
     * 执行 HTTP 请求并统一处理响应
     * 有 $data 则为 POST，无 $data 则为 GET
     * 
     * @param string $url 请求 URL
     * @param string|array|null $data POST 数据，为 null 则为 GET
     * @return array|false 成功返回响应数据数组，失败返回 false
     */
    protected static function fetch(string $url, $data = null): array|false
    {
        $result = HttpClient::request($url, $data, static::getHeaders());
        return ($result['success'] && $result['http_code'] === 200) ? $result : false;
    }

    /**
     * 解析 JSON 数据
     * 
     * @param string $json JSON 字符串
     * @return array|null 解析成功返回数组，失败返回 null
     */
    protected static function parseJson(string $json): ?array
    {
        if (empty($json)) {
            return null;
        }
        
        $data = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    /**
     * 修复 URL，确保协议完整
     * 
     * @param string $url 原始 URL
     * @return string 修复后的 URL
     */
    protected static function fixUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        return strpos($url, 'http') === 0 ? $url : 'https:' . $url;
    }
} 