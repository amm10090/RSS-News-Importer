<?php
if (!defined('ABSPATH')) {
	exit;
}
class RSS_News_Importer_Logger {
	private $log_file;
	private $log_levels = ['debug', 'info', 'warning', 'error'];
	public function __construct() {
		$this->log_file = WP_CONTENT_DIR . '/rss-news-importer-logs.log';
	}
	public function log($message, $level = 'info') {
		if (!in_array($level, $this->log_levels)) {
			$level = 'info';
		}
		$timestamp = current_time('mysql');
		$log_entry = [
		            'date' => $timestamp,
		            'level' => $level,
		            'message' => $message
		        ];
		$formatted_message = sprintf("[%s] %s: %s\n", $timestamp, strtoupper($level), $message);
		error_log($formatted_message, 3, $this->log_file);
		return $log_entry;
	}
	public function get_logs() {
		if (!file_exists($this->log_file)) {
			return [];
		}
		$logs = [];
		$lines = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$log_entry = $this->parse_log_line($line);
			if ($log_entry) {
				$logs[] = $log_entry;
			}
		}
		return array_reverse($logs);
	}
	public function clear_logs() {
		return file_put_contents($this->log_file, '') !== false;
	}
	public function get_log_size() {
		return file_exists($this->log_file) ? filesize($this->log_file) : 0;
	}
	private function parse_log_line($line) {
		$pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+): (.+)/';
		if (preg_match($pattern, $line, $matches)) {
			return [
			                'date' => $matches[1],
			                'level' => strtolower($matches[2]),
			                'message' => $matches[3]
			            ];
		}
		return null;
	}
	public function get_recent_logs($limit = 10, $log_levels = ['error', 'warning', 'info', 'debug']) {
		$logs = $this->get_logs();
		return array_slice(array_filter($logs, function($log) use ($log_levels) {
			return in_array($log['level'], $log_levels);
		}
		), 0, $limit);
	}
	public function export_logs_to_csv($file_path) {
		$logs = $this->get_logs();
		$fp = fopen($file_path, 'w');
		if ($fp === false) {
			return false;
		}
		fputcsv($fp, ['Date', 'Level', 'Message']);
		foreach ($logs as $log) {
			fputcsv($fp, [$log['date'], $log['level'], $log['message']]);
		}
		fclose($fp);
		return true;
	}
	public function delete_old_logs($days = 30) {
		$logs = $this->get_logs();
		$cutoff_date = strtotime("-$days days");
		$new_logs = array_filter($logs, function($log) use ($cutoff_date) {
			return strtotime($log['date']) > $cutoff_date;
		}
		);
		$log_content = '';
		foreach ($new_logs as $log) {
			$log_content .= sprintf("[%s] %s: %s\n", $log['date'], strtoupper($log['level']), $log['message']);
		}
		return file_put_contents($this->log_file, $log_content) !== false;
	}
    //获取最新日志
	public function refresh_logs() {
		return $this->get_logs(); // 直接返回最新的日志
	}

}