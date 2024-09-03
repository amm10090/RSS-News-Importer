<?php
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Cron_Manager
{
    private $plugin_name;
    private $version;
    private $cron_hook = 'rss_news_importer_update_hook';
    private $logger;
    private $importer;
    private $options;
    private $cache;


    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
        $this->cache = new RSS_News_Importer_Cache($plugin_name, $version, $this->logger);
        $this->importer = new RSS_News_Importer_Post_Importer($plugin_name, $version, $this->cache);
        $this->options = get_option('rss_news_importer_options', array());
    }

    public function init()
    {
        add_action($this->cron_hook, array($this, 'execute_rss_update'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));
    }

    public function schedule_update($recurrence = 'hourly')
    {
        if ($recurrence === 'custom') {
            $interval = get_option('rss_news_importer_options')['custom_cron_interval'] ?? 60;
            $recurrence = 'every_' . $interval . '_minutes';

            if (!wp_next_scheduled($this->cron_hook)) {
                wp_schedule_event(time(), $recurrence, $this->cron_hook);
            }

            $this->logger->log("自定义RSS更新任务已计划，间隔: $interval 分钟", 'info');
        } else {
            if (!wp_next_scheduled($this->cron_hook)) {
                wp_schedule_event(time(), $recurrence, $this->cron_hook);
            }
            $this->logger->log("定时RSS更新任务已计划，频率: $recurrence", 'info');
        }
    }

    public function unschedule_update()
    {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
            $this->logger->log("RSS更新任务已取消计划", 'info');
        }
    }

    public function execute_rss_update()
    {
        try {
            $this->logger->log("开始RSS更新任务", 'info');

            $update_method = $this->options['update_method'] ?? 'bulk';
            $feeds = $this->get_rss_feeds();

            if (empty($feeds)) {
                $this->logger->log("没有找到RSS源配置", 'warning');
                return;
            }

            if ($update_method === 'individual') {
                $this->update_feeds_individually($feeds);
            } else {
                $this->update_feeds_bulk($feeds);
            }

            $this->logger->log("RSS更新任务完成", 'info');
        } catch (Exception $e) {
            $this->logger->log("RSS更新任务发生错误: " . $e->getMessage(), 'error');
        }
    }

    private function update_feeds_individually($feeds)
    {
        foreach ($feeds as $feed) {
            $this->logger->log("正在更新源: {$feed['url']}", 'info');
            try {
                $result = $this->importer->import_feed($feed['url']);
                $this->logger->log("更新源 {$feed['url']} 完成: 导入 $result 篇文章", 'info');
            } catch (Exception $e) {
                $this->logger->log("更新源 {$feed['url']} 时出错: " . $e->getMessage(), 'error');
            }
        }
    }

    private function update_feeds_bulk($feeds)
    {
        try {
            if (empty($feeds)) {
                $this->logger->log("没有可用的RSS源进行更新", 'warning');
                return;
            }
            $result = $this->importer->import_all_feeds($feeds);
            $this->logger->log("批量更新源完成: 共导入 $result 篇文章", 'info');
        } catch (Exception $e) {
            $this->logger->log("批量更新源时出错: " . $e->getMessage(), 'error');
        }
    }

    public function get_current_schedule()
    {
        $crons = _get_cron_array();
        foreach ($crons as $timestamp => $cron) {
            if (isset($cron[$this->cron_hook])) {
                return key($cron[$this->cron_hook]);
            }
        }
        return false;
    }

    public function get_next_scheduled_time()
    {
        return wp_next_scheduled($this->cron_hook);
    }

    public function update_schedule($schedule)
    {
        $this->unschedule_update();
        $result = $this->schedule_update($schedule);
        if ($result === false) {
            error_log('Failed to schedule update: ' . $schedule);
        }
        $this->logger->log("RSS更新计划已更新为: $schedule", 'info');
        return $result;
    }

    public function add_custom_cron_interval($schedules)
    {
        $custom_interval = $this->options['custom_cron_interval'] ?? null;
        if ($custom_interval) {
            $schedules['rss_custom_interval'] = array(
                'interval' => $custom_interval * 60,
                'display' => sprintf(__('每 %d 分钟', 'rss-news-importer'), $custom_interval)
            );
        }
        return $schedules;
    }

    public function get_rss_feeds()
    {
        return $this->options['rss_feeds'] ?? array();
    }

    public function is_update_due()
    {
        $last_update = get_option('rss_news_importer_last_update', 0);
        $update_interval = $this->get_update_interval();
        return (time() - $last_update) >= $update_interval;
    }

    private function get_update_interval()
    {
        $schedule = $this->get_current_schedule();
        if ($schedule === 'rss_custom_interval') {
            return ($this->options['custom_cron_interval'] ?? 60) * 60; // 转换分钟为秒
        }
        $schedules = wp_get_schedules();
        return $schedules[$schedule]['interval'] ?? 3600; // 默认为每小时
    }

    public function maybe_update_feeds()
    {
        if ($this->is_update_due()) {
            $this->execute_rss_update();
            update_option('rss_news_importer_last_update', time());
        }
    }

    public function get_cron_status()
    {
        return array(
            'next_scheduled' => $this->get_next_scheduled_time(),
            'current_schedule' => $this->get_current_schedule(),
            'last_update' => get_option('rss_news_importer_last_update', 0),
            'update_method' => $this->options['update_method'] ?? 'bulk'
        );
    }

    public function is_wp_cron_enabled()
    {
        return !(defined('DISABLE_WP_CRON') && True);
    }

    public function get_cron_hook()
    {
        return $this->cron_hook;
    }

    public function manual_update()
    {
        $this->logger->log("开始手动更新RSS源", 'info');

        try {
            $feeds = $this->get_rss_feeds();

            if (empty($feeds)) {
                $this->logger->log("没有找到RSS源配置", 'warning');
                return false;
            }

            $total_imported = 0;
            $update_method = $this->options['update_method'] ?? 'bulk';

            foreach ($feeds as $feed) {
                $feed_url = is_array($feed) ? $feed['url'] : $feed;
                $this->logger->log("正在手动更新源: {$feed_url}", 'info');

                try {
                    // 强制刷新,跳过缓存
                    if ($update_method === 'individual') {
                        $result = $this->importer->import_feed($feed_url, true);
                        $this->logger->log("手动更新源 {$feed_url} 完成: 导入 {$result} 篇文章", 'info');
                        $total_imported += $result;
                    } else {
                        // 如果是批量更新,我们需要确保importer的import_all_feeds方法也支持强制刷新
                        $result = $this->importer->import_feed($feed_url, true);
                        $total_imported += $result;
                    }
                } catch (Exception $e) {
                    $this->logger->log("手动更新源 {$feed_url} 时出错: " . $e->getMessage(), 'error');
                }
            }

            if ($update_method === 'bulk') {
                $this->logger->log("批量手动更新完成: 共导入 {$total_imported} 篇文章", 'info');
            }

            $this->logger->log("手动更新RSS任务完成,共导入 {$total_imported} 篇文章", 'info');
            return $total_imported;
        } catch (Exception $e) {
            $this->logger->log("手动更新RSS任务失败: " . $e->getMessage(), 'error');
            return false;
        }
    }
}
