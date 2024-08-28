<?php

// 如果直接访问此文件,则中止执行
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

    /**
     * 构造函数：初始化定时任务管理器
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
    }

    /**
     * 获取cron钩子名称
     */
    public function get_cron_hook()
    {
        return $this->cron_hook;
    }

    /**
     * 安排RSS导入任务
     */
    public function schedule_import($recurrence = 'hourly')
    {
        if (!wp_next_scheduled($this->get_cron_hook())) {
            wp_schedule_event(time(), $recurrence, $this->get_cron_hook());
        }
    }

    /**
     * 取消RSS导入任务
     */
    public function unschedule_import()
    {
        $timestamp = wp_next_scheduled($this->get_cron_hook());
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->get_cron_hook());
        }
    }

    /**
     * 执行RSS导入任务
     */
    public function run_tasks()
    {
        $this->logger->log("开始执行RSS导入任务", 'info');

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $importer = new RSS_News_Importer_Post_Importer($this->plugin_name, $this->version);

        foreach ($feeds as $feed) {
            $feed_url = is_array($feed) ? $feed['url'] : $feed;
            $feed_name = is_array($feed) && isset($feed['name']) ? $feed['name'] : $feed_url;

            $this->logger->log("开始导入RSS源: $feed_name", 'info');
            $result = $importer->import_feed($feed_url);
            $this->logger->log("完成导入RSS源: $feed_name, 导入 $result 篇文章", 'info');
        }

        $this->logger->log("RSS导入任务执行完成", 'info');
    }

    /**
     * 获取下次计划任务的执行时间
     */
    public function get_next_scheduled_time()
    {
        return wp_next_scheduled($this->get_cron_hook());
    }

    /**
     * 更新任务计划
     */
    public function update_schedule($new_recurrence)
    {
        $this->unschedule_import();
        $this->schedule_import($new_recurrence);
    }

    /**
     * 获取当前计划
     */
    public function get_current_schedule()
    {
        $timestamp = wp_next_scheduled($this->get_cron_hook());
        if ($timestamp) {
            $recurrence = wp_get_schedule($this->get_cron_hook());
            return $recurrence ? $recurrence : 'manual';
        }
        return 'manual';
    }

    /**
     * 手动运行导入任务
     */
    public function run_import_now()
    {
        $this->run_tasks();
    }

    /**
     * 获取所有可用的定时任务间隔
     */
    public function get_available_schedules()
    {
        $schedules = wp_get_schedules();
        $available_schedules = array();
        foreach ($schedules as $key => $schedule) {
            $available_schedules[$key] = $schedule['display'];
        }
        return $available_schedules;
    }

    /**
     * 添加自定义定时任务间隔
     */
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

    /**
     * 初始化定时任务钩子
     */
    public function init_cron_hooks()
    {
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        add_action($this->get_cron_hook(), array($this, 'run_tasks'));
    }

    /**
     * 检查定时任务是否正常运行
     */
    public function check_cron_health()
    {
        $last_run = get_option('rss_news_importer_last_cron_run');
        $current_time = time();
        $threshold = 2 * 3600; // 2 hours

        if ($last_run && ($current_time - $last_run) > $threshold) {
            $this->logger->log("Cron可能未正常运行，上次运行时间: " . date('Y-m-d H:i:s', $last_run), 'warning');
            // 这里可以添加发送警告邮件的代码
        }
    }

    /**
     * 更新最后运行时间
     */
    public function update_last_run_time()
    {
        update_option('rss_news_importer_last_cron_run', time());
    }

    /**
     * 获取定时任务状态
     */
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

    /**
     * 重置定时任务
     */
    public function reset_cron()
    {
        $this->unschedule_import();
        $this->schedule_import();
        $this->logger->log("定时任务已重置", 'info');
    }

    /**
     * 获取所有计划任务
     */
    public function get_all_scheduled_tasks()
    {
        $cron_array = _get_cron_array();
        $tasks = array();

        foreach ($cron_array as $timestamp => $cron) {
            foreach ($cron as $hook => $dings) {
                foreach ($dings as $key => $data) {
                    $tasks[] = array(
                        'hook' => $hook,
                        'timestamp' => $timestamp,
                        'schedule' => $data['schedule'],
                        'args' => $data['args']
                    );
                }
            }
        }

        return $tasks;
    }

    /**
     * 清理过期的定时任务
     */
    public function clean_expired_crons()
    {
        $cron_array = _get_cron_array();
        $current_time = time();
        $cleaned = false;

        foreach ($cron_array as $timestamp => $cron) {
            if ($timestamp < $current_time) {
                unset($cron_array[$timestamp]);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            _set_cron_array($cron_array);
            $this->logger->log("已清理过期的定时任务", 'info');
        }
    }
}
