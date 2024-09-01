<?php

if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Dashboard_Manager {
    private $post_importer;
    private $queue_manager;
    private $logger;
    private $cache;
    private $cron_manager;

    /**
     * 构造函数
     * 
     * @param RSS_News_Importer_Post_Importer $post_importer 文章导入器实例
     * @param RSS_News_Importer_Queue $queue_manager 队列管理器实例
     * @param RSS_News_Importer_Logger $logger 日志记录器实例
     * @param RSS_News_Importer_Cache $cache 缓存管理器实例
     * @param RSS_News_Importer_Cron_Manager $cron_manager 定时任务管理器实例
     */
    public function __construct($post_importer, $queue_manager, $logger, $cache, $cron_manager) {
        $this->post_importer = $post_importer;
        $this->queue_manager = $queue_manager;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->cron_manager = $cron_manager;

        $this->check_dependencies();
    }

    /**
     * 检查依赖项是否正确
     */
    private function check_dependencies() {
        if (!$this->post_importer instanceof RSS_News_Importer_Post_Importer) {
            $this->log_error('post_importer 不是有效的 RSS_News_Importer_Post_Importer 实例');
        }
        if (!$this->queue_manager instanceof RSS_News_Importer_Queue) {
            $this->log_error('queue_manager 不是有效的 RSS_News_Importer_Queue 实例');
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
    }

    /**
     * 记录错误
     * 
     * @param string $message 错误消息
     */
    private function log_error($message) {
        if ($this->logger instanceof RSS_News_Importer_Logger) {
            $this->logger->log($message, 'error');
        } else {
            error_log('RSS News Importer Error: ' . $message);
        }
    }

    /**
     * 获取导入统计信息
     * 
     * @return array 包含今日、本周、总导入数量和最后导入时间的数组
     */
    public function get_import_statistics() {
        try {
            return [
                'today' => $this->post_importer->get_imports_count_for_period('today'),
                'week' => $this->post_importer->get_imports_count_for_period('week'),
                'total' => $this->post_importer->get_total_imports_count(),
                'last_import' => $this->post_importer->get_last_import_time()
            ];
        } catch (Exception $e) {
            $this->log_error('获取导入统计信息时发生错误：' . $e->getMessage());
            return [
                'today' => 0,
                'week' => 0,
                'total' => 0,
                'last_import' => 'Unknown'
            ];
        }
    }

    /**
     * 获取RSS源列表
     * 
     * @return array 包含RSS源信息的数组
     */
    public function get_rss_feeds() {
        try {
            $feeds = get_option('rss_news_importer_options')['rss_feeds'] ?? [];
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

    /**
     * 获取队列状态
     * 
     * @return array 包含队列状态信息的数组
     */
    public function get_queue_status() {
        try {
            $queue_size = $this->queue_manager->get_queue_size();
            $max_queue_size = $this->queue_manager->get_max_queue_size();
            $usage_percentage = ($max_queue_size > 0) ? ($queue_size / $max_queue_size) * 100 : 0;

            return [
                'current_size' => $queue_size,
                'max_size' => $max_queue_size,
                'usage_percentage' => round($usage_percentage, 2)
            ];
        } catch (Exception $e) {
            $this->log_error('获取队列状态时发生错误：' . $e->getMessage());
            return [
                'current_size' => 0,
                'max_size' => 0,
                'usage_percentage' => 0
            ];
        }
    }

    /**
     * 获取系统状态
     * 
     * @return array 包含系统状态信息的数组
     */
    public function get_system_status() {
        try {
            return [
                'wp_cron_status' => $this->cron_manager->is_wp_cron_enabled() ? 'Enabled' : 'Disabled',
                'cache_usage' => $this->cache->get_cache_usage(),
                'log_file_size' => $this->logger->get_log_size()
            ];
        } catch (Exception $e) {
            $this->log_error('获取系统状态时发生错误：' . $e->getMessage());
            return [
                'wp_cron_status' => 'Unknown',
                'cache_usage' => 'Unknown',
                'log_file_size' => 0
            ];
        }
    }

    /**
     * 获取性能指标
     * 
     * @return array 包含性能指标的数组
     */
    public function get_performance_metrics() {
        try {
            return [
                'average_import_time' => $this->post_importer->get_average_import_time(),
                'successful_imports' => $this->post_importer->get_successful_imports_count(),
                'failed_imports' => $this->post_importer->get_failed_imports_count()
            ];
        } catch (Exception $e) {
            $this->log_error('获取性能指标时发生错误：' . $e->getMessage());
            return [
                'average_import_time' => 0,
                'successful_imports' => 0,
                'failed_imports' => 0
            ];
        }
    }

    /**
     * 获取最近导入的文章
     * 
     * @param int $limit 限制数量
     * @return array 最近导入的文章列表
     */
    public function get_recent_imports($limit = 5) {
        try {
            return $this->post_importer->get_recent_imports($limit);
        } catch (Exception $e) {
            $this->log_error('获取最近导入的文章时发生错误：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取最近的错误和警告
     * 
     * @param int $limit 限制数量
     * @return array 最近的错误和警告列表
     */
    public function get_recent_errors_and_warnings($limit = 5) {
        try {
            $logs = $this->logger->get_logs();
            if (!is_array($logs)) {
                return [];
            }
            $filtered_logs = array_filter($logs, function($log) {
                return in_array(strtolower($log['level']), ['error', 'warning']);
            });
            return array_slice($filtered_logs, 0, $limit);
        } catch (Exception $e) {
            $this->log_error('获取最近的错误和警告时发生错误：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取通知
     * 
     * @return array 包含系统通知的数组
     */
    public function get_notifications() {
        $notifications = [];

        try {
            $queue_status = $this->get_queue_status();
            if ($queue_status['usage_percentage'] > 90) {
                $notifications[] = '队列接近满载，当前使用率：' . $queue_status['usage_percentage'] . '%';
            }

            $log_file_size = $this->logger->get_log_size();
            if ($log_file_size > 10 * 1024 * 1024) { // 如果日志文件大于10MB
                $notifications[] = '日志文件过大，当前大小：' . size_format($log_file_size);
            }

            $recent_logs = $this->get_recent_errors_and_warnings(5);
            foreach ($recent_logs as $log) {
                $notifications[] = '[' . $log['date'] . '] ' . strtoupper($log['level']) . ': ' . $log['message'];
            }
        } catch (Exception $e) {
            $this->log_error('获取通知时发生错误：' . $e->getMessage());
            $notifications[] = '获取系统通知时发生错误';
        }

        return $notifications;
    }

    /**
     * 获取RSS源健康状态
     * 
     * @param array|string $feed RSS源数据
     * @return string 健康状态
     */
    private function get_feed_health($feed) {
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
            return 'Error';
        }
    }

    /**
     * 获取RSS源最后更新时间
     * 
     * @param array|string $feed RSS源数据
     * @return string|false 最后更新时间或false
     */
    private function get_feed_last_update($feed) {
        try {
            $feed_url = is_array($feed) ? $feed['url'] : $feed;
            return get_option('rss_feed_last_update_' . md5($feed_url), false);
        } catch (Exception $e) {
            $this->log_error('获取RSS源最后更新时间时发生错误：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取RSS源最近导入数量
     * 
     * @param string $feed_url RSS源URL
     * @return int 最近导入数量
     */
private function get_feed_recent_imports($feed_url) {
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

    /**
     * 运行导入任务
     * 
     * @return bool 是否成功运行导入任务
     */
    public function run_import_task() {
        try {
            return $this->cron_manager->run_import_task();
        } catch (Exception $e) {
            $this->log_error('运行导入任务时发生错误：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 清空队列
     * 
     * @return bool 是否成功清空队列
     */
    public function clear_queue() {
        try {
            return $this->queue_manager->clear_queue();
        } catch (Exception $e) {
            $this->log_error('清空队列时发生错误：' . $e->getMessage());
            return false;
        }
    }

    /**
     * 重置所有RSS源
     * 
     * @return bool 是否成功重置所有RSS源
     */
    public function reset_all_feeds() {
        try {
            $feeds = get_option('rss_news_importer_options')['rss_feeds'] ?? [];
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

    /**
     * 清理日志
     * 
     * @return bool 是否成功清理日志
     */
    public function clear_logs() {
        try {
            return $this->logger->clear_logs();
        } catch (Exception $e) {
            $this->log_error('清理日志时发生错误：' . $e->getMessage());
            return false;
        }
    }
    //获取系统信息
    public function get_system_info() {
    return array(
        'php_version' => phpversion(),
        'wp_version' => get_bloginfo('version'),
        'plugin_version' => RSS_NEWS_IMPORTER_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'wp_debug' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
        'wp_cron' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Disabled' : 'Enabled'
    );
}
}