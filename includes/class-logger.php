<?php

/**
 * Handles logging for the RSS News Importer.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Logger {
    private $log_file;
    private $max_log_size = 5242880; // 5MB in bytes

    // 构造函数，初始化日志文件路径
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/rss-news-importer-log.txt';
    }

    // 记录日志信息
    public function log($message, $level = 'info') {
        $timestamp = current_time('mysql');
        $formatted_message = sprintf("[%s] [%s]: %s\n", $timestamp, strtoupper($level), $message);
        
        // 检查日志文件大小是否超过最大限制
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->rotate_logs();
        }
        
        error_log($formatted_message, 3, $this->log_file);
    }

    // 获取日志信息
    public function get_logs($limit = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }

        $logs = array();
        $lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // 最近的日志在前

        foreach ($lines as $line) {
            if (count($logs) >= $limit) break;

            if (preg_match('/\[(.*?)\] \[(.*?)\]: (.*)/', $line, $matches)) {
                $logs[] = array(
                    'date' => $matches[1],
                    'type' => $matches[2],
                    'message' => $matches[3]
                );
            }
        }

        return $logs;
    }

    // 轮换日志文件
    private function rotate_logs() {
        $backup_file = $this->log_file . '.1';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        rename($this->log_file, $backup_file);
    }

    // 清除日志文件
public function clear_logs() {
    if (file_exists($this->log_file)) {
        return unlink($this->log_file);
    }
    return true; // 如果文件不存在,也视为成功
}
}