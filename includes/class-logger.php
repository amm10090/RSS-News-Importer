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
    return $this->parse_logs();
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
        $logs = $this->parse_logs();
        return array_slice(array_filter($logs, function($log) use ($log_types) {
            return in_array($log['type'], $log_types);
        }), 0, $limit);
    }

    // 解析日志文件
    private function parse_logs() {
        $logs = array();
        if (file_exists($this->log_file)) {
            $lines = file($this->log_file);
            foreach ($lines as $line) {
                $log_entry = $this->parse_log_line($line);
                if ($log_entry) {
                    $logs[] = $log_entry;
                }
            }
        }
        return $logs;
    }

    // 解析单行日志
    public function parse_log_line($line) {
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
        $logs = $this->parse_logs();
        return count(array_filter($logs, function($log) use ($type) {
            return $log['type'] === strtolower($type);
        }));
    }

    // 获取最后一条日志记录的时间
    public function get_last_log_time() {
        $logs = $this->parse_logs();
        if (!empty($logs)) {
            return end($logs)['date'];
        }
        return null;
    }

    // 导出日志到CSV文件
    public function export_logs_to_csv($file_path) {
        if (!file_exists($this->log_file)) {
            return false;
        }

        $logs = $this->parse_logs();
        $fp = fopen($file_path, 'w');

        if ($fp === false) {
            return false;
        }

        fputcsv($fp, array('Date', 'Type', 'Message'));

        foreach ($logs as $log_entry) {
            fputcsv($fp, array($log_entry['date'], $log_entry['type'], $log_entry['message']));
        }

        fclose($fp);
        return true;
    }

    // 删除旧的日志记录
    public function delete_old_logs($days = 30) {
        if (!file_exists($this->log_file)) {
            return true;
        }

        $logs = $this->parse_logs();
        $cutoff_date = strtotime("-$days days");

        $new_logs = array_filter($logs, function($log) use ($cutoff_date) {
            return strtotime($log['date']) > $cutoff_date;
        });

        $log_lines = array_map(function($log) {
            return sprintf("[%s] %s: %s\n", $log['date'], strtoupper($log['type']), $log['message']);
        }, $new_logs);

        file_put_contents($this->log_file, implode('', $log_lines));
        return true;
    }
}