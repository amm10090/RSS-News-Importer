<?php

// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'partials/class-rss-news-importer-dashboard.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-dashboard-manager.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-admin-ajax.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-menu.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-settings.php';

class RSS_News_Importer_Admin {
    private $plugin_name;
    private $version;
    private $core;
    private $cron_manager;
    private $logger;
    private $importer;
    private $settings;
    private $ajax;
    private $display;
    private $option_name = 'rss_news_importer_options';
    private $dashboard;
    private $dashboard_manager;
    private $ajax_handler;
    private $queue_manager;
    private $menu;
    private $cache; // 确保这行存在


    /**
     * 构造函数
     * 
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cron_manager = new RSS_News_Importer_Cron_Manager($plugin_name, $version);
        $this->logger = new RSS_News_Importer_Logger();
        $this->importer = new RSS_News_Importer_Post_Importer($plugin_name, $version);
        $this->cache = new RSS_News_Importer_Cache($this->plugin_name, $this->version); // 修复这一行
        $this->queue_manager = new RSS_News_Importer_Queue(); 
        $this->ajax_handler = new RSS_News_Importer_Admin_Ajax($this);
        $this->menu = new RSS_News_Importer_Menu($plugin_name, $version, $this);
        $this->settings = new RSS_News_Importer_Settings($plugin_name, $version, $this->option_name, $this);

        $this->init_hooks();
        $this->init_dashboard();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks() {
        add_action('admin_init', array($this->settings, 'register_settings'));
        add_action($this->cron_manager->get_cron_hook(), array($this->cron_manager, 'run_tasks'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        if (false === get_option($this->option_name)) {
            add_option($this->option_name, array());
        }

        add_filter('plugin_action_links_' . plugin_basename(RSS_NEWS_IMPORTER_PLUGIN_DIR . 'rss-news-importer.php'), array($this, 'add_settings_link'));
    }

    /**
     * 初始化仪表板
     */
    private function init_dashboard() {
        if (!$this->dashboard_manager) {
            $this->dashboard_manager = new RSS_News_Importer_Dashboard_Manager(
                $this->importer,
                $this->queue_manager,
                $this->logger,
                $this->cache,
                $this->cron_manager
            );
        }
        if (!$this->dashboard) {
            $this->dashboard = new RSS_News_Importer_Dashboard($this->dashboard_manager);
        }
    }
    public function prepare_dashboard() {
        $this->init_dashboard();
    }

    /**
     * 加载管理页面样式
     * 
     * @param string $hook 当前 WordPress 页面的钩子后缀
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
            // 添加新的仪表盘样式
            wp_enqueue_style(
                'rss-news-importer-dashboard',
                plugin_dir_url(__FILE__) . 'css/rss-news-importer-dashboard.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * 加载管理页面脚本
     * 
     * @param string $hook 当前 WordPress 页面的钩子后缀
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery', 'jquery-ui-sortable'), $this->version, false);
            wp_localize_script($this->plugin_name, 'rss_news_importer_ajax', $this->get_ajax_data());
            $this->enqueue_react_scripts();
        }
    }

    /**
     * 加载 React 脚本
     */
    private function enqueue_react_scripts() {
        wp_enqueue_script('react', 'https://unpkg.com/react@17.0.2/umd/react.production.min.js', array(), '17.0.2', false);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17.0.2/umd/react-dom.production.min.js', array('react'), '17.0.2', false);
        wp_enqueue_script('log-viewer-component', plugin_dir_url(__FILE__) . 'js/log-viewer-component.js', array('react', 'react-dom'), $this->version, false);
    }

    /**
     * 获取 AJAX 数据
     * 
     * @return array AJAX 数据
     */
    private function get_ajax_data() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rss_news_importer_nonce'),
            'i18n' => array(
            'add_feed_prompt' => __('Enter the URL of the RSS feed you want to add:', 'rss-news-importer'),
            'remove_text' => __('Remove', 'rss-news-importer'),
            'importing_text' => __('Importing...', 'rss-news-importer'),
            'error_text' => __('An error occurred. Please try again.', 'rss-news-importer'),
            'running_text' => __('Running...', 'rss-news-importer'),
            'run_now_text' => __('Run Now', 'rss-news-importer'),
            'save_settings_nonce' => wp_create_nonce('rss_news_importer_save_settings')
            )
        );
    }

    /**
     * 处理设置更新
     */
    public function handle_settings_update() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('Settings Saved', 'rss-news-importer'), 'updated');
        }
        settings_errors('rss_news_importer_messages');
    }

    /**
     * 处理定时任务设置保存
     */
    public function handle_cron_settings_save() {
        if (isset($_POST['rss_news_importer_cron_schedule'])) {
            $new_schedule = sanitize_text_field($_POST['rss_news_importer_cron_schedule']);
            $this->cron_manager->update_schedule($new_schedule);
        }
    }

    /**
     * 获取 RSS 源项目的 HTML
     * 
     * @param array $feed RSS 源数据
     * @return string HTML 内容
     */
    public function get_feed_item_html($feed) {
        ob_start();
        ?>
        <div class="feed-item" data-feed-url="<?php echo esc_attr($feed['url']); ?>">
            <span class="dashicons dashicons-menu handle"></span>
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_url($feed['url']); ?>" readonly class="feed-url">
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_attr($feed['name']); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
            <button class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
            <button class="button preview-feed"><?php _e('Preview', 'rss-news-importer'); ?></button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 添加设置链接到插件页面
     * 
     * @param array $links 现有的插件链接
     * @return array 修改后的插件链接
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=' . $this->plugin_name) . '">' . __('Settings', 'rss-news-importer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * 获取插件名称
     * 
     * @return string 插件名称
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * 获取插件版本
     * 
     * @return string 插件版本
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * 获取选项名称
     * 
     * @return string 选项名称
     */
    public function get_option_name() {
        return $this->option_name;
    }

    /**
     * 获取缓存实例
     * 
     * @return RSS_News_Importer_Cache 缓存实例
     */
    public function get_cache() {
        return $this->cache;
    }

    /**
     * 获取队列管理器实例
     * 
     * @return RSS_News_Importer_Queue 队列管理器实例
     */
    public function get_queue_manager() {
        return $this->queue_manager;
    }

    /**
     * 获取定时任务管理器实例
     * 
     * @return RSS_News_Importer_Cron_Manager 定时任务管理器实例
     */
    public function get_cron_manager() {
        return $this->cron_manager;
    }

    /**
     * 获取日志记录器实例
     * 
     * @return RSS_News_Importer_Logger 日志记录器实例
     */
    public function get_logger() {
        return $this->logger;
    }

    /**
     * 获取仪表板实例
     * 
     * @return RSS_News_Importer_Dashboard 仪表板实例
     */
    public function get_dashboard() {
        return $this->dashboard;
    }
}