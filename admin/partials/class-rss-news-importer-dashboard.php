<?php

// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Dashboard
{





    // 仪表板管理器实例
    private $dashboard_manager;

    // 构造函数
    public function __construct($dashboard_manager)
    {
        $this->dashboard_manager = $dashboard_manager;
    }

    // 显示仪表板
    public function display_dashboard()
    {
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有足够的权限访问此页面。'));
        }


?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->display_notifications(); ?>

            <div class="rss-dashboard-container">
                <?php
                $this->display_import_statistics();
                $this->display_queue_status();
                $this->display_system_status();
                $this->display_performance_metrics();
                $this->display_quick_actions();
                $this->display_system_info();
                $this->display_rss_feed_status();
                $this->display_recent_imports();
                $this->display_recent_errors_and_warnings();
                ?>
            </div>
        </div>
        <?php
    }

    // 显示通知
    private function display_notifications()
    {
        $notifications = $this->dashboard_manager->get_notifications();
        if (!empty($notifications)) {
            echo '<div class="notice notice-warning is-dismissible">';
            foreach ($notifications as $notification) {
                echo '<p>' . esc_html($notification) . '</p>';
            }
            echo '</div>';
        }
    }

    // 显示导入统计
    private function display_import_statistics()
    {
        try {
            $stats = $this->dashboard_manager->get_import_statistics();
        ?>
            <div class="dashboard-card">
                <h2><?php _e('导入统计', 'rss-news-importer'); ?></h2>
                <p><?php _e('今日导入：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($stats['today'] ?? 0); ?></span></p>
                <p><?php _e('本周导入：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($stats['week'] ?? 0); ?></span></p>
                <p><?php _e('总导入：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($stats['total'] ?? 0); ?></span></p>
                <p><?php _e('最后导入时间：', 'rss-news-importer'); ?><?php echo esc_html($stats['last_import'] ?? __('从未', 'rss-news-importer')); ?></p>
            </div>
        <?php
        } catch (Exception $e) {
            error_log('获取导入统计信息时出错：' . $e->getMessage());
            echo '<p class="error">' . __('获取导入统计信息时出错', 'rss-news-importer') . '</p>';
        }
    }

    // 显示队列状态
    private function display_queue_status()
    {
        $queue_status = $this->dashboard_manager->get_queue_status();
        ?>
        <div class="dashboard-card">
            <h2><?php _e('导入队列状态', 'rss-news-importer'); ?></h2>
            <p><?php _e('当前队列项目数：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($queue_status['current_size'] ?? 0); ?></span></p>
            <p><?php _e('队列容量使用：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($queue_status['usage_percentage'] ?? 0); ?>%</span></p>
            <p><?php _e('下次计划执行：', 'rss-news-importer'); ?><?php echo esc_html($queue_status['next_scheduled'] ?? __('未计划', 'rss-news-importer')); ?></p>
        </div>
    <?php
    }

    // 显示系统状态
    private function display_system_status()
    {
        $system_status = $this->dashboard_manager->get_system_status();
    ?>
        <div class="dashboard-card">
            <h2><?php _e('系统状态', 'rss-news-importer'); ?></h2>
            <p><?php _e('WordPress Cron状态：', 'rss-news-importer'); ?><?php echo esc_html($system_status['wp_cron_status'] ?? __('未知', 'rss-news-importer')); ?></p>
            <p><?php _e('缓存使用情况：', 'rss-news-importer'); ?><?php echo esc_html($system_status['cache_usage'] ?? __('未知', 'rss-news-importer')); ?></p>
            <p><?php _e('日志文件大小：', 'rss-news-importer'); ?><?php echo esc_html(size_format($system_status['log_file_size'] ?? 0)); ?></p>
        </div>
    <?php
    }

    // 显示性能指标
    private function display_performance_metrics()
    {
        $metrics = $this->dashboard_manager->get_performance_metrics();
    ?>
        <div class="dashboard-card">
            <h2><?php _e('性能指标', 'rss-news-importer'); ?></h2>
            <p><?php _e('平均导入时间：', 'rss-news-importer'); ?><?php echo esc_html($metrics['average_import_time'] ?? __('未知', 'rss-news-importer')); ?> <?php _e('秒', 'rss-news-importer'); ?></p>
            <p><?php _e('每小时导入数量：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($metrics['imports_per_hour'] ?? 0); ?></span></p>
            <p><?php _e('每天导入数量：', 'rss-news-importer'); ?><span class="stat-value"><?php echo esc_html($metrics['imports_per_day'] ?? 0); ?></span></p>
        </div>
    <?php
    }

    // 显示快速操作
    private function display_quick_actions()
    {
    ?>
        <div class="dashboard-card">
            <h2><?php _e('快速操作', 'rss-news-importer'); ?></h2>
            <form method="post" action="" id="rss-news-importer-dashboard-form">
                <?php wp_nonce_field('rss_news_importer_dashboard_actions', 'rss_news_importer_dashboard_nonce'); ?>
                <div class="quick-actions-grid">
                    <input type="submit" name="run_import" class="dashboard-button" value="<?php _e('立即运行导入', 'rss-news-importer'); ?>">
                    <input type="submit" name="clear_queue" class="dashboard-button" value="<?php _e('清空队列', 'rss-news-importer'); ?>">
                    <input type="submit" name="reset_feeds" class="dashboard-button" value="<?php _e('重置所有RSS源', 'rss-news-importer'); ?>">
                    <input type="submit" name="clear_logs" class="dashboard-button" value="<?php _e('清理日志', 'rss-news-importer'); ?>">
                </div>
            </form>
        </div>
    <?php
    }

    // 显示系统信息
    private function display_system_info()
    {
        $system_info = $this->dashboard_manager->get_system_info();
    ?>
        <div class="dashboard-card">
            <h2><?php _e('系统信息', 'rss-news-importer'); ?></h2>
            <p><?php _e('PHP版本：', 'rss-news-importer'); ?><?php echo esc_html($system_info['php_version'] ?? __('未知', 'rss-news-importer')); ?></p>
            <p><?php _e('WordPress版本：', 'rss-news-importer'); ?><?php echo esc_html($system_info['wp_version'] ?? __('未知', 'rss-news-importer')); ?></p>
            <p><?php _e('插件版本：', 'rss-news-importer'); ?><?php echo esc_html($system_info['plugin_version'] ?? __('未知', 'rss-news-importer')); ?></p>
            <p><?php _e('内存限制：', 'rss-news-importer'); ?><?php echo esc_html($system_info['memory_limit'] ?? __('未知', 'rss-news-importer')); ?></p>
            <p><?php _e('最大执行时间：', 'rss-news-importer'); ?><?php echo esc_html($system_info['max_execution_time'] ?? __('未知', 'rss-news-importer')); ?> <?php _e('秒', 'rss-news-importer'); ?></p>
        </div>
    <?php
    }

    // 显示RSS源状态
    private function display_rss_feed_status()
    {
        $feeds = $this->dashboard_manager->get_rss_feeds();
    ?>
        <div class="dashboard-card full-width">
            <h2><?php _e('RSS源状态', 'rss-news-importer'); ?></h2>
            <p><?php _e('活跃的RSS源数量：', 'rss-news-importer'); ?><span class="stat-value"><?php echo count($feeds); ?></span></p>
            <div class="table-responsive">
                <table class="rss-feed-table">
                    <thead>
                        <tr>
                            <th><?php _e('源URL', 'rss-news-importer'); ?></th>
                            <th><?php _e('健康状态', 'rss-news-importer'); ?></th>
                            <th><?php _e('上次更新', 'rss-news-importer'); ?></th>
                            <th><?php _e('最近导入数量', 'rss-news-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeds as $feed): ?>
                            <tr>
                                <td><?php echo esc_url($feed['url']); ?></td>
                                <td><?php echo esc_html($feed['health'] ?? __('未知', 'rss-news-importer')); ?></td>
                                <td><?php echo esc_html($feed['last_update'] ?? __('未知', 'rss-news-importer')); ?></td>
                                <td><?php echo esc_html($feed['recent_imports'] ?? __('未知', 'rss-news-importer')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php
    }

    // 显示最近导入的文章
    private function display_recent_imports()
    {
        $recent_imports = $this->dashboard_manager->get_recent_imports();
    ?>
        <div class="dashboard-card full-width">
            <h2><?php _e('最近导入的文章', 'rss-news-importer'); ?></h2>
            <?php if (!empty($recent_imports)): ?>
                <ul class="recent-imports-list">
                    <?php foreach ($recent_imports as $import): ?>
                        <li>
                            <div class="import-title"><?php echo esc_html($import['title']); ?></div>
                            <div class="import-date"><?php echo esc_html($import['import_date']); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('暂无最近导入的文章。', 'rss-news-importer'); ?></p>
            <?php endif; ?>
        </div>
    <?php
    }

    // 显示最近的错误和警告
    private function display_recent_errors_and_warnings()
    {
        $logs = $this->dashboard_manager->get_recent_errors_and_warnings();
    ?>
        <div class="dashboard-card">
            <h2><?php _e('最近的错误和警告', 'rss-news-importer'); ?></h2>
            <?php if (!empty($logs)): ?>
                <ul>
                    <?php foreach ($logs as $log): ?>
                        <li>
                            [<?php echo esc_html($log['date']); ?>]
                            <?php echo esc_html(strtoupper($log['level'])); ?>:
                            <?php echo esc_html($log['message']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('暂无最近的错误和警告。', 'rss-news-importer'); ?></p>
            <?php endif; ?>
        </div>
<?php
    }

    // 处理仪表板操作
    public function process_dashboard_actions()
    {
        if (!isset($_POST['rss_news_importer_dashboard_nonce']) || !wp_verify_nonce($_POST['rss_news_importer_dashboard_nonce'], 'rss_news_importer_dashboard_actions')) {
            return;
        }

        if (isset($_POST['run_import'])) {
            $results = $this->dashboard_manager->run_import_task();
            $this->add_admin_notice(__('导入任务已完成。请查看日志了解详情。', 'rss-news-importer'), 'success');
        }

        if (isset($_POST['clear_queue'])) {
            $cleared = $this->dashboard_manager->clear_queue();
            if ($cleared) {
                $this->add_admin_notice(__('队列已清空。', 'rss-news-importer'), 'success');
            } else {
                $this->add_admin_notice(__('清空队列失败。', 'rss-news-importer'), 'error');
            }
        }

        if (isset($_POST['reset_feeds'])) {
            $reset = $this->dashboard_manager->reset_all_feeds();
            if ($reset) {
                $this->add_admin_notice(__('所有RSS源已重置。', 'rss-news-importer'), 'success');
            } else {
                $this->add_admin_notice(__('重置RSS源失败。', 'rss-news-importer'), 'error');
            }
        }

        if (isset($_POST['clear_logs'])) {
            $cleared = $this->dashboard_manager->clear_logs();
            if ($cleared) {
                $this->add_admin_notice(__('日志已清理。', 'rss-news-importer'), 'success');
            } else {
                $this->add_admin_notice(__('清理日志失败。', 'rss-news-importer'), 'error');
            }
        }
    }

    // 添加管理通知
    private function add_admin_notice($message, $leve = 'info')
    {
        add_settings_error(
            'rss_news_importer_dashboard_messages',
            '',
            $message,
            $leve
        );
    }
}
