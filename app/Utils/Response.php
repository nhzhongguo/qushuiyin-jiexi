<?php

namespace App\Utils;

/**
 * API 响应工具类
 * 统一处理 REST API 响应格式
 */
class Response
{
    /**
     * 成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $httpCode HTTP 状态码
     * @return void
     */
    public static function success($data = null, string $message = 'success', int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => true,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * 错误响应
     *
     * @param string $message 错误消息
     * @param int $httpCode HTTP 状态码
     * @param mixed $errors 详细错误信息（可选）
     * @return void
     */
    public static function error(string $message, int $httpCode = 400, $errors = null): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * 验证错误响应
     *
     * @param array $errors 验证错误数组，格式为 ['field' => 'message']
     * @return void
     */
    public static function validationError(array $errors): void
    {
        // 提取第一个错误消息作为主要消息
        $message = !empty($errors) ? reset($errors) : '参数验证失败';
        self::error($message, 422);
    }
}

