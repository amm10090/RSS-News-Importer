<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin_Display
{
    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    // 显示插件设置页面
    public function display_plugin_setup_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'rss-news-importer'));
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'feeds';
?>
        <div class="wrap rss-news-importer-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo $this->plugin_name; ?>&tab=feeds" class="nav-tab <?php echo $active_tab == 'feeds' ? 'nav-tab-active' : ''; ?>"><?php _e('RSS Feeds', 'rss-news-importer'); ?></a>
                <a href="?page=<?php echo $this->plugin_name; ?>&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'rss-news-importer'); ?></a>
                <a href="?page=<?php echo $this->plugin_name; ?>&tab=import" class="nav-tab <?php echo $active_tab == 'import' ? 'nav-tab-active' : ''; ?>"><?php _e('Import', 'rss-news-importer'); ?></a>
                <a href="?page=<?php echo $this->plugin_name; ?>&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e('Logs', 'rss-news-importer'); ?></a>
            </h2>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'feeds':
                        $this->display_feeds_tab();
                        break;
                    case 'settings':
                        $this->display_settings_tab();
                        break;
                    case 'import':
                        $this->display_import_tab();
                        break;
                    case 'logs':
                        $this->display_logs_tab();
                        break;
                }
                ?>
            </div>
        </div>
    <?php
    }

    // 显示RSS源标签页
    private function display_feeds_tab()
    {
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
    ?>
        <div id="feeds" class="tab-pane active">
            <div class="card">
                <h2 class="title"><?php _e('Manage RSS Feeds', 'rss-news-importer'); ?></h2>
                <div class="inside">
                    <div id="rss-feeds-list" class="sortable-list">
                        <?php
                        if (empty($feeds)) {
                            echo '<p class="no-feeds">' . __('No RSS feeds added yet.', 'rss-news-importer') . '</p>';
                        } else {
                            foreach ($feeds as $feed) :
                                $feed_url = isset($feed['url']) ? $feed['url'] : '';
                                $feed_name = isset($feed['name']) ? $feed['name'] : '';
                                if (empty($feed_url)) continue;
                        ?>
                                <div class="feed-item" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                                    <div class="feed-item-content">
                                        <span class="dashicons dashicons-menu handle"></span>
                                        <input type="text" value="<?php echo esc_url($feed_url); ?>" readonly class="feed-url">
                                        <input type="text" value="<?php echo esc_attr($feed_name); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
                                        <div class="feed-actions">
                                            <button class="button button-secondary preview-feed" title="<?php _e('Preview', 'rss-news-importer'); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </button>
                                            <button class="button button-secondary remove-feed" title="<?php _e('Remove', 'rss-news-importer'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="feed-preview"></div>
                                </div>
                        <?php
                            endforeach;
                        }
                        ?>
                    </div>
                    <div class="rss-feed-actions">
                        <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>">
                        <input type="text" id="new-feed-name" placeholder="<?php _e('Enter feed name (optional)', 'rss-news-importer'); ?>">
                        <button id="add-feed" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php _e('Add Feed', 'rss-news-importer'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    // 显示设置标签页
    private function display_settings_tab()
    {
    ?>
        <div id="settings" class="tab-pane">
            <form method="post" action="options.php" id="rss-news-importer-form">
                <?php
                settings_fields($this->plugin_name);
                do_settings_sections($this->plugin_name);
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    // 显示导入标签页
    private function display_import_tab()
    {
    ?>
        <div id="import" class="tab-pane">
            <div class="card">
                <h2 class="title"><?php _e('Import Now', 'rss-news-importer'); ?></h2>
                <div class="inside">
                    <p><?php _e('Click the button below to manually import posts from all configured RSS feeds.', 'rss-news-importer'); ?></p>
                    <button id="import-now" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Import Now', 'rss-news-importer'); ?>
                    </button>
                    <div id="import-progress" class="progress-bar" style="display:none;"></div>
                    <div id="import-results"></div>
                </div>
            </div>
        </div>
    <?php
    }

    // 显示日志标签页
    private function display_logs_tab()
    {
    ?>
        <div id="logs" class="tab-pane">
            <div id="log-viewer-root"></div>
        </div>
    <?php
    }

    // 显示仪表板页面
    public function display_dashboard_page()
    {
        $dashboard = new RSS_News_Importer_Dashboard($this->plugin_name, $this->version);
        $dashboard->display_dashboard();
    }

    // 显示定时任务设置页面
    public function display_cron_settings_page()
    {
        $cron_manager = new RSS_News_Importer_Cron_Manager($this->plugin_name, $this->version);
        $current_schedule = $cron_manager->get_current_schedule();
        $next_run = $cron_manager->get_next_scheduled_time();
        $available_schedules = $cron_manager->get_available_schedules();

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cron-settings-display.php';
    }
}