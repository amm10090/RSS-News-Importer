<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin_Core
{
    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    // 初始化钩子
    public function init_hooks()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
    }

    // 加载管理样式
    public function enqueue_styles($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
        }
    }

    // 加载管理脚本
    public function enqueue_scripts($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery', 'jquery-ui-sortable'), $this->version, false);
            wp_localize_script($this->plugin_name, 'rss_news_importer_ajax', $this->get_ajax_data());

            // 加载 React 和 ReactDOM
            $this->enqueue_react_scripts();
        }
    }

    // 加载 React 相关脚本
    private function enqueue_react_scripts()
    {
        wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', array(), '17.0.2', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', array('react'), '17.0.2', true);
        wp_enqueue_script('log-viewer-component', plugin_dir_url(__FILE__) . 'js/log-viewer-component.js', array('react', 'react-dom'), $this->version, true);
    }

    // 获取 AJAX 数据
    private function get_ajax_data()
    {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rss_news_importer_nonce'),
            'i18n' => array(
                'add_feed_prompt' => __('Enter the URL of the RSS feed you want to add:', 'rss-news-importer'),
                'remove_text' => __('Remove', 'rss-news-importer'),
                'importing_text' => __('Importing...', 'rss-news-importer'),
                'error_text' => __('An error occurred. Please try again.', 'rss-news-importer'),
                'running_text' => __('Running...', 'rss-news-importer'),
                'run_now_text' => __('Run Now', 'rss-news-importer')
            )
        );
    }

    // 添加插件管理菜单
    public function add_plugin_admin_menu()
    {
        add_menu_page(
            __('RSS News Importer', 'rss-news-importer'),
            __('RSS News Importer', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page'),
            'dashicons-rss',
            100
        );

        add_submenu_page(
            $this->plugin_name,
            __('RSS Feeds Dashboard', 'rss-news-importer'),
            __('Dashboard', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-dashboard',
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Cron Settings', 'rss-news-importer'),
            __('Cron Settings', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-cron-settings',
            array($this, 'display_cron_settings_page')
        );
    }

    // 显示插件设置页面
    public function display_plugin_setup_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->handle_settings_update();
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-display.php';
    }

    // 显示仪表板页面
    public function display_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $dashboard = new RSS_News_Importer_Dashboard($this->plugin_name, $this->version);
        $dashboard->display_dashboard();
    }

    // 显示定时任务设置页面
    public function display_cron_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cron_manager = new RSS_News_Importer_Cron_Manager($this->plugin_name, $this->version);
        $current_schedule = $cron_manager->get_current_schedule();
        $next_run = $cron_manager->get_next_scheduled_time();
        $available_schedules = $cron_manager->get_available_schedules();

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cron-settings-display.php';
    }

    // 处理设置更新
    private function handle_settings_update()
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('Settings Saved', 'rss-news-importer'), 'updated');
        }
        settings_errors('rss_news_importer_messages');
    }
}