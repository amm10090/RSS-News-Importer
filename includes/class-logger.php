<?php

if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Logger {
    private $log_file;

    // 构造函数，初始化日志文件路径
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/rss-news-importer-logs.log';
    }

    // 记录日志信息
    public function log($message, $level = 'info') {
        $timestamp = current_time('mysql');
        $formatted_message = sprintf("[%s] %s: %s\n", $timestamp, strtoupper($level), $message);
        error_log($formatted_message, 3, $this->log_file);
    }

    // 获取所有日志内容
    public function get_logs() {
        if (file_exists($this->log_file)) {
            return file_get_contents($this->log_file);
        }
        return '';
    }

    // 清除所有日志
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }

    // 获取最近的日志记录
    public function get_recent_logs($limit = 10, $log_types = array('error', 'warning', 'info')) {
        $logs = array();
        if (file_exists($this->log_file)) {
            $lines = array_reverse(file($this->log_file));
            $count = 0;
            foreach ($lines as $line) {
                if ($count >= $limit) {
                    break;
                }
                $log_entry = $this->parse_log_line($line);
                if ($log_entry && in_array($log_entry['type'], $log_types)) {
                    $logs[] = $log_entry;
                    $count++;
                }
            }
        }
        return $logs;
    }

    // 解析单行日志
    private function parse_log_line($line) {
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+): (.+)/';
        if (preg_match($pattern, $line, $matches)) {
            return array(
                'date' => $matches[1],
                'type' => strtolower($matches[2]),
                'message' => trim($matches[3])
            );
        }
        return null;
    }

    // 获取日志文件大小
    public function get_log_size() {
        if (file_exists($this->log_file)) {
            return filesize($this->log_file);
        }
        return 0;
    }

    // 获取特定类型的日志数量
    public function get_log_count_by_type($type) {
        $count = 0;
        if (file_exists($this->log_file)) {
            $lines = file($this->log_file);
            foreach ($lines as $line) {
                $log_entry = $this->parse_log_line($line);
                if ($log_entry && $log_entry['type'] === strtolower($type)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    // 获取最后一条日志记录的时间
    public function get_last_log_time() {
        if (file_exists($this->log_file)) {
            $lines = file($this->log_file);
            $last_line = end($lines);
            $log_entry = $this->parse_log_line($last_line);
            if ($log_entry) {
                return $log_entry['date'];
            }
        }
        return null;
    }

    // 导出日志到CSV文件
    public function export_logs_to_csv($file_path) {
        if (!file_exists($this->log_file)) {
            return false;
        }

        $logs = $this->get_logs();
        $lines = explode("\n", trim($logs));
        $fp = fopen($file_path, 'w');

        if ($fp === false) {
            return false;
        }

        fputcsv($fp, array('Date', 'Type', 'Message'));

        foreach ($lines as $line) {
            $log_entry = $this->parse_log_line($line);
            if ($log_entry) {
                fputcsv($fp, array($log_entry['date'], $log_entry['type'], $log_entry['message']));
            }
        }

        fclose($fp);
        return true;
    }

    // 删除旧的日志记录
    public function delete_old_logs($days = 30) {
        if (!file_exists($this->log_file)) {
            return true;
        }

        $logs = file($this->log_file);
        $new_logs = array();
        $cutoff_date = strtotime("-$days days");

        foreach ($logs as $log) {
            $log_entry = $this->parse_log_line($log);
            if ($log_entry && strtotime($log_entry['date']) > $cutoff_date) {
                $new_logs[] = $log;
            }
        }

        file_put_contents($this->log_file, implode('', $new_logs));
        return true;
    }
}