<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Cron_Manager
{
    private $plugin_name;
    private $version;
    private $cron_hook = 'rss_news_importer_cron_hook';
    private $option_name = 'rss_news_importer_options';
    private $logger;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
    }

    // 获取cron钩子名称
    public function get_cron_hook()
    {
        return $this->cron_hook;
    }

    // 安排RSS导入任务
    public function schedule_import($recurrence = 'hourly')
    {
        if (!wp_next_scheduled($this->get_cron_hook())) {
            wp_schedule_event(time(), $recurrence, $this->get_cron_hook());
            $this->logger->log("Scheduled import task with recurrence: $recurrence", 'info');
        }
    }

    // 取消RSS导入任务
    public function unschedule_import()
    {
        $timestamp = wp_next_scheduled($this->get_cron_hook());
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->get_cron_hook());
            $this->logger->log("Unscheduled import task", 'info');
        }
    }

    // 执行RSS导入任务
    public function run_tasks()
    {
        $this->logger->log("Starting RSS import task", 'info');

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $importer = new RSS_News_Importer_Post_Importer($this->plugin_name, $this->version);

        foreach ($feeds as $feed) {
            $feed_url = is_array($feed) ? $feed['url'] : $feed;
            $feed_name = is_array($feed) && isset($feed['name']) ? $feed['name'] : $feed_url;

            $this->logger->log("Importing from feed: $feed_name", 'info');
            $result = $importer->import_feed($feed_url);
            $this->logger->log("Imported $result posts from feed: $feed_name", 'info');
        }

        $this->logger->log("Completed RSS import task", 'info');
        $this->update_last_run_time();
    }

    // 获取下次计划任务的执行时间
    public function get_next_scheduled_time()
    {
        return wp_next_scheduled($this->get_cron_hook());
    }

    // 更新任务计划
    public function update_schedule($new_recurrence)
    {
        $this->unschedule_import();
        $this->schedule_import($new_recurrence);
        $this->logger->log("Updated import schedule to: $new_recurrence", 'info');
    }

    // 获取当前计划
    public function get_current_schedule()
    {
        $timestamp = wp_next_scheduled($this->get_cron_hook());
        if ($timestamp) {
            $recurrence = wp_get_schedule($this->get_cron_hook());
            return $recurrence ? $recurrence : 'manual';
        }
        return 'manual';
    }

    // 手动运行导入任务
    public function run_import_now()
    {
        $this->run_tasks();
        $this->logger->log("Manually ran import task", 'info');
    }

    // 获取所有可用的定时任务间隔
    public function get_available_schedules()
    {
        $schedules = wp_get_schedules();
        $available_schedules = array();
        foreach ($schedules as $key => $schedule) {
            $available_schedules[$key] = $schedule['display'];
        }
        return $available_schedules;
    }

    // 添加自定义定时任务间隔
    public function add_custom_schedules($schedules)
    {
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => __('Once Weekly', 'rss-news-importer')
        );
        $schedules['monthly'] = array(
            'interval' => 2635200,
            'display' => __('Once a month', 'rss-news-importer')
        );
        return $schedules;
    }

    // 初始化定时任务钩子
    public function init_cron_hooks()
    {
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        add_action($this->get_cron_hook(), array($this, 'run_tasks'));
    }

    // 更新最后运行时间
    private function update_last_run_time()
    {
        update_option('rss_news_importer_last_cron_run', time());
    }

    // 获取定时任务状态
    public function get_cron_status()
    {
        $next_scheduled = $this->get_next_scheduled_time();
        $current_schedule = $this->get_current_schedule();
        $last_run = get_option('rss_news_importer_last_cron_run');

        return array(
            'next_scheduled' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled',
            'current_schedule' => $current_schedule,
            'last_run' => $last_run ? date('Y-m-d H:i:s', $last_run) : 'Never run'
        );
    }
}