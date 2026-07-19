<?php
// utils/info.php

/**
 * 通用信息响应类（零耦合）
 *
 * 用于返回正常业务流程中的信息提示，如操作成功、状态变更通知等。
 * HTTP 状态码默认为 200，可根据需要调整。
 */
class Info
{
    /**
     * @var bool 是否显示调试信息（如文件、行号）
     */
    protected $debug = false;

    /**
     * @var callable|null 日志记录回调
     */
    protected $logger = null;

    /**
     * @var int 默认 HTTP 状态码
     */
    protected $defaultHttpCode = 200;

    /**
     * @var string 默认消息文本
     */
    protected $defaultMessage = '操作成功';

    /**
     * 构造函数
     *
     * @param bool $debug 是否显示调试信息
     * @param callable|null $logger 日志记录器
     * @param int $defaultHttpCode 默认状态码
     * @param string $defaultMessage 默认消息
     */
    public function __construct(
        bool $debug = false,
        ?callable $logger = null,
        int $defaultHttpCode = 200,
        string $defaultMessage = '操作成功'
    ) {
        $this->debug = $debug;
        $this->logger = $logger;
        $this->defaultHttpCode = $defaultHttpCode;
        $this->defaultMessage = $defaultMessage;
    }

    /**
     * 设置调试模式
     *
     * @param bool $debug
     * @return $this
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * 设置日志记录器
     *
     * @param callable $logger
     * @return $this
     */
    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 返回一个信息响应（JSON）
     *
     * @param string $message 信息文本
     * @param int $httpCode HTTP 状态码（默认 200）
     * @param mixed $data 附加数据（可选，如返回的 ID、列表等）
     * @param string $level 信息级别（如 'info', 'success', 'notice'）
     * @return string JSON 响应
     */
    public function send(
        string $message,
        int $httpCode = 200,
        $data = null,
        string $level = 'info'
    ): string {
        $httpCode = $httpCode ?? $this->defaultHttpCode;

        $response = [
            'info'    => true,
            'level'   => $level,
            'message' => $message,
        ];

        // 如果有数据，添加到响应中
        if ($data !== null) {
            $response['data'] = $data;
        }

        // 调试模式附加调用信息
        if ($this->debug) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? $trace[0] ?? null;
            if ($caller) {
                $response['file'] = $caller['file'] ?? 'unknown';
                $response['line'] = $caller['line'] ?? 0;
            }
        }

        // 记录日志（如果设置了 logger）
        if ($this->logger) {
            call_user_func($this->logger, $response, $httpCode);
        }

        http_response_code($httpCode);
        header('Content-Type: application/json');
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 快捷方法：返回成功信息（level = success）
     *
     * @param string $message
     * @param mixed $data
     * @param int $httpCode
     * @return string
     */
    public function success(string $message = '成功', $data = null, int $httpCode = 200): string
    {
        return $this->send($message, $httpCode, $data, 'success');
    }

    /**
     * 快捷方法：返回普通信息（level = info）
     *
     * @param string $message
     * @param mixed $data
     * @param int $httpCode
     * @return string
     */
    public function info(string $message, $data = null, int $httpCode = 200): string
    {
        return $this->send($message, $httpCode, $data, 'info');
    }

    /**
     * 快捷方法：返回通知（level = notice）
     *
     * @param string $message
     * @param mixed $data
     * @param int $httpCode
     * @return string
     */
    public function notice(string $message, $data = null, int $httpCode = 200): string
    {
        return $this->send($message, $httpCode, $data, 'notice');
    }

    /**
     * 快捷方法：返回状态信息（如操作状态）
     *
     * @param bool $status 操作是否成功
     * @param string $successMessage 成功时的消息
     * @param string $failMessage 失败时的消息
     * @param mixed $data
     * @param int $httpCode
     * @return string
     */
    public function status(bool $status, string $successMessage = '成功', string $failMessage = '失败', $data = null, int $httpCode = 200): string
    {
        $message = $status ? $successMessage : $failMessage;
        $level = $status ? 'success' : 'notice';
        return $this->send($message, $httpCode, $data, $level);
    }

    /**
     * 静态方法：快速返回成功（使用默认配置）
     *
     * @param string $message
     * @param mixed $data
     * @param int $httpCode
     * @return string
     */
    public static function quickSuccess(string $message = '成功', $data = null, int $httpCode = 200): string
    {
        $instance = new self(false, null, $httpCode);
        return $instance->success($message, $data, $httpCode);
    }

    /**
     * 静态方法：快速返回普通信息
     *
     * @param string $message
     * @param mixed $data
     * @param int $httpCode
     * @return string
     */
    public static function quickInfo(string $message, $data = null, int $httpCode = 200): string
    {
        $instance = new self(false, null, $httpCode);
        return $instance->info($message, $data, $httpCode);
    }
}