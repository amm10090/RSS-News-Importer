<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Logger
{
    private $log_file;
    private $max_log_size = 5242880; // 5MB in bytes
    private $log_levels = array('debug', 'info', 'warning', 'error', 'critical');

    /**
     * 构造函数，初始化日志文件路径
     */
    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/rss-news-importer-log.txt';
    }

    /**
     * 记录日志信息
     *
     * @param string $message 日志消息
     * @param string $level 日志级别 (debug, info, warning, error, critical)
     */
    public function log($message, $level = 'info')
    {
        if (!in_array($level, $this->log_levels)) {
            $level = 'info';
        }

        $timestamp = current_time('mysql');
        $formatted_message = sprintf("[%s] [%s]: %s\n", $timestamp, strtoupper($level), $message);

        // 检查日志文件大小是否超过最大限制
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->rotate_logs();
        }

        error_log($formatted_message, 3, $this->log_file);
    }

    /**
     * 获取日志信息
     *
     * @param int $limit 要获取的日志条目数量
     * @param string $level 筛选特定级别的日志 (可选)
     * @return array 日志条目数组
     */
    public function get_logs($limit = 100, $level = null)
    {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $logs = array();
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // 最近的日志在前

        foreach ($lines as $line) {
            if (count($logs) >= $limit) break;

            if (preg_match('/\[(.*?)\] \[(.*?)\]: (.*)/', $line, $matches)) {
                if ($level && strtolower($matches[2]) !== strtolower($level)) {
                    continue;
                }
                $logs[] = array(
                    'date' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3]
                );
            }
        }

        return $logs;
    }

    /**
     * 轮换日志文件
     */
    private function rotate_logs()
    {
        $backup_file = $this->log_file . '.1';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        rename($this->log_file, $backup_file);
    }

    /**
     * 清除日志文件
     *
     * @return bool 是否成功清除日志
     */
    public function clear_logs()
    {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true; // 如果文件不存在，也视为成功
    }

    /**
     * 设置最大日志文件大小
     *
     * @param int $size 最大文件大小（字节）
     */
    public function set_max_log_size($size)
    {
        $this->max_log_size = intval($size);
    }

    /**
     * 获取日志文件大小
     *
     * @return int 日志文件大小（字节）
     */
    public function get_log_size()
    {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }

    /**
     * 获取日志文件路径
     *
     * @return string 日志文件路径
     */
    public function get_log_file_path()
    {
        return $this->log_file;
    }

    /**
     * 导出日志文件
     *
     * @return string|bool 日志内容或失败时返回false
     */
    public function export_logs()
    {
        if (file_exists($this->log_file)) {
            return file_get_contents($this->log_file);
        }
        return false;
    }

    /**
     * 检查日志文件是否可写
     *
     * @return bool 日志文件是否可写
     */
    public function is_log_writable()
    {
        return is_writable(dirname($this->log_file)) && (!file_exists($this->log_file) || is_writable($this->log_file));
    }
}