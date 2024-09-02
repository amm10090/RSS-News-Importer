<?php
if (!defined('ABSPATH')) {
	exit;
}
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-cron-manager.php';

class RSS_News_Importer_Dashboard_Manager
{
	private $post_importer;
	private $logger;
	private $cache;
	private $cron_manager;
	private $ajax;
	private $queue;

	// 构造函数
	public function __construct($post_importer, $queue, $logger, $cache, $cron_manager, $ajax)
	{
		$this->post_importer = $post_importer;
		$this->queue = $queue;
		$this->logger = $logger;
		$this->cache = $cache;
		$this->cron_manager = $cron_manager;
		$this->ajax = $ajax;
		$this->check_dependencies();
	}

	// 检查依赖项
	private function check_dependencies()
	{
		if (!$this->post_importer instanceof RSS_News_Importer_Post_Importer) {
			$this->log_error('post_importer 不是有效的 RSS_News_Importer_Post_Importer 实例');
		}
		if (!$this->logger instanceof RSS_News_Importer_Logger) {
			$this->log_error('logger 不是有效的 RSS_News_Importer_Logger 实例');
		}
		if (!$this->cache instanceof RSS_News_Importer_Cache) {
			$this->log_error('cache 不是有效的 RSS_News_Importer_Cache 实例');
		}
		if (!$this->cron_manager instanceof RSS_News_Importer_Cron_Manager) {
			$this->log_error('cron_manager 不是有效的 RSS_News_Importer_Cron_Manager 实例');
		}
		if (!$this->queue instanceof RSS_News_Importer_Queue) {
			$this->log_error('queue 不是有效的 RSS_News_Importer_Queue 实例');
		}
	}

	// 记录错误信息
	private function log_error($message)
	{
		if ($this->logger instanceof RSS_News_Importer_Logger) {
			$this->logger->log($message, 'error');
		} else {
			error_log('RSS News Importer Error: ' . $message);
		}
	}

	// 获取导入统计信息
	public function get_import_statistics()
	{
		try {
			$stats = $this->post_importer->get_import_stats();
			return [
				'today' => $stats['imports_today'] ?? 0,
				'week' => $stats['imports_week'] ?? 0,
				'total' => $stats['total_imports'] ?? 0,
				'last_import' => $stats['last_import'] ?? __('从未', 'rss-news-importer')
			];
		} catch (Exception $e) {
			$this->log_error('获取导入统计信息时发生错误：' . $e->getMessage());
			return [
				'today' => 0,
				'week' => 0,
				'total' => 0,
				'last_import' => __('未知', 'rss-news-importer')
			];
		}
	}

	// 获取队列状态
	public function get_queue_status()
	{
		try {
			$queue_size = $this->queue->get_queue_size();
			$max_queue_size = $this->queue->get_max_queue_size();
			$usage_percentage = ($max_queue_size > 0) ? ($queue_size / $max_queue_size) * 100 : 0;
			$next_scheduled = $this->cron_manager->get_next_scheduled_time();
			return [
				'current_size' => $queue_size,
				'max_size' => $max_queue_size,
				'usage_percentage' => round($usage_percentage, 2),
				'next_scheduled' => $next_scheduled ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled) : __('未计划', 'rss-news-importer')
			];
		} catch (Exception $e) {
			$this->log_error('获取队列状态时发生错误：' . $e->getMessage());
			return [
				'current_size' => 0,
				'max_size' => 0,
				'usage_percentage' => 0,
				'next_scheduled' => __('未知', 'rss-news-importer')
			];
		}
	}

	// 获取系统状态
	public function get_system_status()
	{
		try {
			return [
				'wp_cron_status' => $this->cron_manager->is_wp_cron_enabled() ? __('启用', 'rss-news-importer') : __('禁用', 'rss-news-importer'),
				'cache_usage' => $this->cache->get_cache_usage(),
				'log_file_size' => $this->logger->get_log_size()
			];
		} catch (Exception $e) {
			$this->log_error('获取系统状态时发生错误：' . $e->getMessage());
			return [
				'wp_cron_status' => __('未知', 'rss-news-importer'),
				'cache_usage' => __('未知', 'rss-news-importer'),
				'log_file_size' => 0
			];
		}
	}

	// 获取性能指标
	public function get_performance_metrics()
	{
		try {
			$stats = $this->post_importer->get_import_stats();
			return [
				'average_import_time' => $stats['average_import_time'] ?? 0,
				'imports_per_hour' => $stats['imports_per_hour'] ?? 0,
				'imports_per_day' => $stats['imports_per_day'] ?? 0
			];
		} catch (Exception $e) {
			$this->log_error('获取性能指标时发生错误：' . $e->getMessage());
			return [
				'average_import_time' => 0,
				'imports_per_hour' => 0,
				'imports_per_day' => 0
			];
		}
	}

	// 获取最近导入的文章
	public function get_recent_imports($limit = 5)
	{
		try {
			return $this->post_importer->get_recent_imports($limit);
		} catch (Exception $e) {
			$this->log_error('获取最近导入的文章时发生错误：' . $e->getMessage());
			return [];
		}
	}

	// 获取最近的错误和警告日志
	public function get_recent_errors_and_warnings($limit = 5)
	{
		try {
			return $this->logger->get_recent_logs($limit, ['error', 'warning']);
		} catch (Exception $e) {
			$this->log_error('获取最近的错误和警告时发生错误：' . $e->getMessage());
			return [];
		}
	}

	// 获取系统通知
	public function get_notifications()
	{
		$notifications = [];
		try {
			$queue_status = $this->get_queue_status();
			if ($queue_status['usage_percentage'] > 90) {
				$notifications[] = sprintf(__('队列接近满载，当前使用率：%s%%', 'rss-news-importer'), $queue_status['usage_percentage']);
			}

			$log_file_size = $this->logger->get_log_size();
			if ($log_file_size > 10 * 1024 * 1024) {
				$notifications[] = sprintf(__('日志文件过大，当前大小：%s', 'rss-news-importer'), size_format($log_file_size));
			}

			$recent_logs = $this->get_recent_errors_and_warnings(5);
			foreach ($recent_logs as $log) {
				$notifications[] = sprintf('[%s] %s: %s', $log['date'], strtoupper($log['level']), $log['message']);
			}
		} catch (Exception $e) {
			$this->log_error('获取通知时发生错误：' . $e->getMessage());
			$notifications[] = __('获取系统通知时发生错误', 'rss-news-importer');
		}
		return $notifications;
	}

	// 获取RSS源状态
	public function get_rss_feeds()
	{
		try {
			$feeds = $this->post_importer->get_rss_feeds();
			$feed_status = [];
			foreach ($feeds as $feed) {
				$feed_url = is_array($feed) ? $feed['url'] : $feed;
				$feed_status[] = [
					'url' => $feed_url,
					'health' => $this->get_feed_health($feed),
					'last_update' => $this->get_feed_last_update($feed),
					'recent_imports' => $this->get_feed_recent_imports($feed_url)
				];
			}
			return $feed_status;
		} catch (Exception $e) {
			$this->log_error('获取RSS源状态时发生错误：' . $e->getMessage());
			return [];
		}
	}

	// 获取RSS源健康状态
	private function get_feed_health($feed)
	{
		try {
			$last_update = $this->get_feed_last_update($feed);
			if (!$last_update) {
				return 'Unknown';
			}
			$time_diff = time() - strtotime($last_update);
			if ($time_diff < 24 * 60 * 60) {
				return 'Good';
			} elseif ($time_diff < 72 * 60 * 60) {
				return 'Warning';
			} else {
				return 'Critical';
			}
		} catch (Exception $e) {
			$this->log_error('获取RSS源健康状态时发生错误：' . $e->getMessage());
			return 'Unknown';
		}
	}

	// 获取RSS源最后更新时间
	private function get_feed_last_update($feed)
	{
		$feed_url = is_array($feed) ? $feed['url'] : $feed;
		$last_update = $this->cache->get_feed_last_update($feed_url);
		return $last_update ? date('Y-m-d H:i:s', $last_update) : 'Unknown';
	}

	// 获取RSS源最近导入数量
	private function get_feed_recent_imports($feed_url)
	{
		try {
			global $wpdb;
			$table_name = $wpdb->prefix . 'rss_news_importer_logs';
			// 检查表是否存在
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
			if (!$table_exists) {
				$this->logger->log("RSS导入日志表不存在", 'error');
				return 0;
			}
			$count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE feed_url = %s AND import_time > %s",
				$feed_url,
				date('Y-m-d H:i:s', strtotime('-24 hours'))
			));
			return intval($count);
		} catch (Exception $e) {
			$this->logger->log('获取RSS源最近导入数量时发生错误：' . $e->getMessage(), 'error');
			return 0;
		}
	}

	// 运行导入任务
	public function run_import_task()
	{
		try {
			$result = $this->cron_manager->execute_rss_update();

			if (!is_array($result)) {
				$this->logger->log('执行RSS更新返回了无效结果', 'error');
				return [
					'success' => false,
					'message' => '执行RSS更新返回了无效结果',
					'show_notice' => true
				];
			}

			return [
				'success' => isset($result['success']) ? $result['success'] : false,
				'message' => isset($result['message']) ? $result['message'] : '未知结果',
				'logs' => isset($result['logs']) ? $result['logs'] : [],
				'show_notice' => true
			];
		} catch (Exception $e) {
			$this->logger->log('运行导入任务时发生错误：' . $e->getMessage(), 'error');
			return [
				'success' => false,
				'message' => $e->getMessage(),
				'show_notice' => true
			];
		}
	}

	// 清空队列
	public function clear_queue()
	{
		try {
			return $this->queue->clear_queue();
		} catch (Exception $e) {
			$this->log_error('清空队列时发生错误：' . $e->getMessage());
			return false;
		}
	}

	// 重置所有RSS源
	public function reset_all_feeds()
	{
		try {
			$feeds = $this->post_importer->get_rss_feeds();
			foreach ($feeds as $feed) {
				$feed_url = is_array($feed) ? $feed['url'] : $feed;
				delete_option('rss_feed_last_update_' . md5($feed_url));
			}
			return true;
		} catch (Exception $e) {
			$this->log_error('重置所有RSS源时发生错误：' . $e->getMessage());
			return false;
		}
	}

	// 清理日志
	public function clear_logs()
	{
		try {
			return $this->logger->clear_logs();
		} catch (Exception $e) {
			$this->log_error('清理日志时发生错误：' . $e->getMessage());
			return false;
		}
	}

	// 获取系统信息
	public function get_system_info()
	{
		return [
			'php_version' => phpversion(),
			'wp_version' => get_bloginfo('version'),
			'plugin_version' => RSS_NEWS_IMPORTER_VERSION,
			'memory_limit' => ini_get('memory_limit'),
			'max_execution_time' => ini_get('max_execution_time'),
			'wp_debug' => defined('WP_DEBUG') && WP_DEBUG ? __('启用', 'rss-news-importer') : __('禁用', 'rss-news-importer'),
			'wp_cron' => $this->cron_manager->is_wp_cron_enabled() ? __('启用', 'rss-news-importer') : __('禁用', 'rss-news-importer')
		];
	}
}
