<?php
/**
 * Manages the cron jobs for RSS News Importer.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Cron_Manager {
    private $plugin_name;
    private $version;
    private $cron_hook = 'rss_news_importer_cron_hook';

    // 构造函数，初始化插件名称和版本
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    // 安排定时任务
    public function schedule_import($recurrence = 'hourly') {
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), $recurrence, $this->cron_hook);
        }
    }

    // 取消定时任务
    public function unschedule_import() {
        $timestamp = wp_next_scheduled($this->cron_hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
        }
    }

    // 激活定时任务
    public function activate($recurrence = 'hourly') {
        $this->schedule_import($recurrence);
    }

    // 停用定时任务
    public function deactivate() {
        $this->unschedule_import();
    }

    // 运行导入器
    public function run_importer() {
        $options = get_option('rss_news_importer_options');
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        $importer = new RSS_News_Importer_Post_Importer();
        
        foreach ($feeds as $feed) {
            $importer->import_feed($feed);
        }
    }

    // 获取下次计划任务的时间
    public function get_next_scheduled_time() {
        return wp_next_scheduled($this->cron_hook);
    }

    // 更新定时任务的计划
    public function update_schedule($new_recurrence) {
        $this->unschedule_import();
        $this->schedule_import($new_recurrence);
    }
}