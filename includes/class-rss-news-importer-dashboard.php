<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Dashboard
{
    // 插件名称
    private $plugin_name;
    
    // 插件版本
    private $version;
    
    // 选项名称
    private $option_name = 'rss_news_importer_options';
    
    // 日志记录器实例
    private $logger;
    
    // 导入器实例
    private $importer;

    /**
     * 构造函数
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
    }

    /**
     * 设置导入器实例
     *
     * @param RSS_News_Importer_Post_Importer $importer 导入器实例
     */
    public function set_importer(RSS_News_Importer_Post_Importer $importer)
    {
        $this->importer = $importer;
    }

    /**
     * 显示仪表板
     */
    public function display_dashboard()
    {
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'rss-news-importer'));
        }

        // 获取统计数据
        $import_stats = $this->get_import_statistics();
        $feed_stats = $this->get_feed_statistics();
        $recent_logs = $this->logger->get_logs(10);  // 获取最近10条日志

        // 包含仪表板视图文件
        include(plugin_dir_path(__FILE__) . 'partials/dashboard-display.php');
    }

    /**
     * 获取导入统计数据
     *
     * @return array 导入统计数据
     */
    private function get_import_statistics()
    {
        $total_imported = get_option('rss_news_importer_total_imported', 0);
        $last_import_time = get_option('rss_news_importer_last_import_time', 0);
        $successful_imports = get_option('rss_news_importer_successful_imports', 0);
        $failed_imports = get_option('rss_news_importer_failed_imports', 0);

        return array(
            'total_imported' => $total_imported,
            'last_import_time' => $last_import_time,
            'successful_imports' => $successful_imports,
            'failed_imports' => $failed_imports
        );
    }

    /**
     * 获取RSS源统计数据
     *
     * @return array RSS源统计数据
     */
    private function get_feed_statistics()
    {
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        return array(
            'total_feeds' => count($feeds),
            'active_feeds' => $this->count_active_feeds($feeds),
            'inactive_feeds' => $this->count_inactive_feeds($feeds)
        );
    }

    /**
     * 统计活跃的RSS源
     *
     * @param array $feeds RSS源数组
     * @return int 活跃RSS源数量
     */
    private function count_active_feeds($feeds)
    {
        return count(array_filter($feeds, function($feed) {
            return isset($feed['active']) && $feed['active'];
        }));
    }

    /**
     * 统计不活跃的RSS源
     *
     * @param array $feeds RSS源数组
     * @return int 不活跃RSS源数量
     */
    private function count_inactive_feeds($feeds)
    {
        return count(array_filter($feeds, function($feed) {
            return !isset($feed['active']) || !$feed['active'];
        }));
    }

    /**
     * 更新RSS源状态
     *
     * @param string $feed_url RSS源URL
     * @param bool $success 是否成功
     */
    public function update_feed_status($feed_url, $success)
    {
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        foreach ($feeds as &$feed) {
            if ($feed['url'] === $feed_url) {
                $feed['active'] = $success;
                $feed['last_update'] = current_time('timestamp');
                break;
            }
        }

        $options['rss_feeds'] = $feeds;
        update_option($this->option_name, $options);
    }

    /**
     * 更新导入统计
     *
     * @param int $imported_count 导入数量
     * @param bool $success 是否成功
     */
    public function update_import_statistics($imported_count, $success = true)
    {
        $total_imported = get_option('rss_news_importer_total_imported', 0);
        $total_imported += $imported_count;
        update_option('rss_news_importer_total_imported', $total_imported);

        update_option('rss_news_importer_last_import_time', current_time('timestamp'));

        if ($success) {
            $successful_imports = get_option('rss_news_importer_successful_imports', 0);
            $successful_imports += $imported_count;
            update_option('rss_news_importer_successful_imports', $successful_imports);
        } else {
            $failed_imports = get_option('rss_news_importer_failed_imports', 0);
            $failed_imports += $imported_count;
            update_option('rss_news_importer_failed_imports', $failed_imports);
        }
    }
}