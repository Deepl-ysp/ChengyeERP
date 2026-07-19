<?php
// utils/log.php

/**
 * 通用日志记录类（零耦合）
 *
 * 功能：
 * - 按级别、按日期存储日志文件（如 debug/2026-07-08.log）
 * - 自动创建目录
 * - 自动清理超过保留天数的旧日志
 * - 支持日志轮转（单个文件超过最大行数时备份并新建）
 * - 支持并发写入（flock）
 */
class Log
{
    /**
     * @var array 配置参数
     */
    protected $config = [];

    /**
     * @var string 当前日志级别
     */
    protected $level;

    /**
     * @var array 日志级别数值映射（用于比较）
     */
    protected $levelMap = [
        'debug'    => 1,
        'info'     => 2,
        'notice'   => 3,
        'warning'  => 4,
        'error'    => 5,
        'critical' => 6,
        'alert'    => 7,
        'emergency'=> 8,
    ];

    /**
     * @var bool 是否已初始化目录
     */
    protected $dirsCreated = false;

    /**
     * 构造函数
     *
     * @param array $config 配置数组（若为空则自动加载 config/log.config.php）
     * @param string $level 全局最低日志级别（可选，覆盖配置）
     */
    public function __construct(array $config = [], string $level = '')
    {
        if (empty($config)) {
            $config = include __DIR__ . '/../config/log.config.php';
        }
        $this->config = array_merge([
            'log_dir' => __DIR__ . '/../log',
            'date_format' => 'Y-m-d',
            'levels' => [],
            'default_level' => 'debug',
            'file_permission' => 0644,
            'log_format' => "[%s] [%s] %s%s",
        ], $config);

        // 确保 levels 中所有配置都有 max_days
        foreach ($this->config['levels'] as $lvl => &$cfg) {
            if (!isset($cfg['max_days'])) {
                $cfg['max_days'] = 30; // 默认保留30天
            }
            if (!isset($cfg['max_lines_per_file'])) {
                $cfg['max_lines_per_file'] = 10000;
            }
        }
        unset($cfg);

        $this->level = $level ?: $this->config['default_level'];

        // 注册析构时清理旧日志（可选，也可以每次写入时检查）
        register_shutdown_function([$this, 'cleanup']);
    }

    /**
     * 写入日志
     *
     * @param string $level   日志级别 (debug, info, warning, error 等)
     * @param string $message 日志消息
     * @param array  $context 上下文数据（将转为 JSON 附加）
     * @return bool 写入是否成功
     */
    public function write(string $level, string $message, array $context = []): bool
    {
        // 检查是否达到该级别的最低要求（如果设置了全局级别限制）
        $currentLevelNum = $this->levelMap[strtolower($level)] ?? 0;
        $minLevelNum = $this->levelMap[strtolower($this->level)] ?? 0;
        if ($currentLevelNum < $minLevelNum) {
            return false; // 低于全局最低级别，不记录
        }

        // 确保目录存在
        if (!$this->ensureDirs($level)) {
            return false;
        }

        // 构建日志行
        $time = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $line = sprintf($this->config['log_format'], $time, strtoupper($level), $message, $contextStr) . "\n";

        // 确定文件路径
        $filePath = $this->getLogFilePath($level);

        // 写入文件（加锁防止并发冲突）
        $result = file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            return false;
        }

        // 检查文件行数，如果超过最大限制则轮转
        $this->rotateIfNeeded($level, $filePath);

        // 清理过期日志（可以每次写入后检查，或定期调用）
        $this->cleanup();

        return true;
    }

    /**
     * 快捷方法：debug 级别
     */
    public function debug(string $message, array $context = []): bool
    {
        return $this->write('debug', $message, $context);
    }

    /**
     * 快捷方法：info 级别
     */
    public function info(string $message, array $context = []): bool
    {
        return $this->write('info', $message, $context);
    }

    /**
     * 快捷方法：warning 级别
     */
    public function warning(string $message, array $context = []): bool
    {
        return $this->write('warning', $message, $context);
    }

    /**
     * 快捷方法：error 级别
     */
    public function error(string $message, array $context = []): bool
    {
        return $this->write('error', $message, $context);
    }

    /**
     * 获取日志文件完整路径（按级别和日期）
     *
     * @param string $level
     * @return string
     */
    protected function getLogFilePath(string $level): string
    {
        $date = date($this->config['date_format']);
        $dir = $this->config['log_dir'] . '/' . strtolower($level);
        $file = strtolower($level) . '-' . $date . '.log';
        return $dir . '/' . $file;
    }

    /**
     * 确保日志目录和子目录存在
     *
     * @param string $level
     * @return bool
     */
    protected function ensureDirs(string $level): bool
    {
        if ($this->dirsCreated) {
            return true;
        }

        $baseDir = $this->config['log_dir'];
        if (!is_dir($baseDir)) {
            if (!mkdir($baseDir, 0755, true)) {
                return false;
            }
        }

        // 为每个级别创建子目录
        foreach (array_keys($this->config['levels']) as $lvl) {
            $subDir = $baseDir . '/' . strtolower($lvl);
            if (!is_dir($subDir)) {
                if (!mkdir($subDir, 0755, true)) {
                    return false;
                }
            }
        }

        $this->dirsCreated = true;
        return true;
    }

    /**
     * 检查文件行数，若超过 max_lines_per_file 则轮转
     *
     * @param string $level
     * @param string $filePath
     */
    protected function rotateIfNeeded(string $level, string $filePath): void
    {
        $maxLines = $this->config['levels'][$level]['max_lines_per_file'] ?? 10000;
        if ($maxLines <= 0) {
            return;
        }

        // 快速检查文件行数（不读取全部内容）
        $lineCount = $this->countLines($filePath);
        if ($lineCount < $maxLines) {
            return;
        }

        // 轮转：将当前文件重命名为带时间戳的备份，然后创建新文件
        $dir = dirname($filePath);
        $basename = basename($filePath, '.log');
        $timestamp = date('Ymd_His');
        $backupFile = $dir . '/' . $basename . '_' . $timestamp . '.log';

        // 加锁以防并发轮转
        $lockFile = $filePath . '.lock';
        $fp = fopen($lockFile, 'w');
        if (flock($fp, LOCK_EX)) {
            // 再次检查行数，避免重复轮转
            if ($this->countLines($filePath) >= $maxLines) {
                // 重命名当前文件
                rename($filePath, $backupFile);
                // 创建新文件（空）
                touch($filePath);
                chmod($filePath, $this->config['file_permission']);
                // 删除过旧的备份文件（保留 max_days 天）
                $this->cleanupBackups($level, $dir);
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * 统计文件行数（快速方法）
     *
     * @param string $filePath
     * @return int
     */
    protected function countLines(string $filePath): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }
        // 使用 shell 命令快速统计（如果有 exec 权限），否则逐行读取
        if (function_exists('exec')) {
            $output = [];
            exec("wc -l < " . escapeshellarg($filePath), $output);
            if (!empty($output)) {
                return (int)trim($output[0]);
            }
        }
        // 降级方案：逐行读取计数（大文件可能慢，但这里通常不会太大）
        $lines = 0;
        $handle = fopen($filePath, 'r');
        if ($handle) {
            while (fgets($handle) !== false) {
                $lines++;
            }
            fclose($handle);
        }
        return $lines;
    }

    /**
     * 清理过期日志文件（按级别保留 max_days 天）
     */
    public function cleanup(): void
    {
        $logDir = $this->config['log_dir'];
        $now = time();

        foreach ($this->config['levels'] as $level => $cfg) {
            $levelDir = $logDir . '/' . strtolower($level);
            if (!is_dir($levelDir)) {
                continue;
            }
            $this->cleanupLevel($level, $levelDir, $cfg['max_days']);
        }
    }

    /**
     * 清理某个级别目录下的过期文件
     *
     * @param string $level
     * @param string $dir
     * @param int    $maxDays
     */
    protected function cleanupLevel(string $level, string $dir, int $maxDays): void
    {
        $files = glob($dir . '/' . strtolower($level) . '-*.log');
        if (!$files) {
            return;
        }

        // 按修改时间排序，保留最新的 maxDays 天
        $now = time();
        $cutoff = strtotime("-{$maxDays} days");

        foreach ($files as $file) {
            // 只处理主日志文件（不含备份的时间戳后缀），备份文件单独清理
            if (preg_match('/' . preg_quote(strtolower($level)) . '-\d{4}-\d{2}-\d{2}\.log$/', basename($file))) {
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                }
            } else {
                // 备份文件（如 *_20260708_123456.log）也按修改时间清理
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * 清理轮转产生的备份文件（在轮转后调用，但也可以由 cleanup 统一处理）
     *
     * @param string $level
     * @param string $dir
     */
    protected function cleanupBackups(string $level, string $dir): void
    {
        // 已经由 cleanupLevel 统一处理，这里可留空
        // 为了保险，立即执行一次清理
        $this->cleanup();
    }

    /**
     * 设置全局最低日志级别
     *
     * @param string $level
     * @return $this
     */
    public function setLevel(string $level): self
    {
        if (isset($this->levelMap[strtolower($level)])) {
            $this->level = $level;
        }
        return $this;
    }

    /**
     * 静态方法：快速记录一条日志（使用默认配置）
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     * @return bool
     */
    public static function quick(string $level, string $message, array $context = []): bool
    {
        static $instance = null;
        if (!$instance) {
            $instance = new self();
        }
        return $instance->write($level, $message, $context);
    }
}