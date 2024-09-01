<?php

if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Menu {
    private $plugin_name;
    private $version;
    private $admin;
    private $option_name;

    public function __construct($plugin_name, $version, $admin) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->admin = $admin;
        $this->option_name = 'rss_news_importer_options';
    }

    public function add_plugin_admin_menu() {
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
            array($this, 'display_dashboard')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Cron Settings', 'rss-news-importer'),
            __('Cron Settings', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-cron-settings',
            array($this, 'display_cron_settings_page')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Import Logs', 'rss-news-importer'),
            __('Import Logs', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-logs',
            array($this, 'display_logs_page')
        );
    }

    public function display_plugin_setup_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $this->admin->handle_settings_update();
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-display.php';
    }

    public function display_dashboard() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $this->admin->prepare_dashboard();
        $this->admin->get_dashboard()->process_dashboard_actions();
        $this->admin->get_dashboard()->display_dashboard();
    }

    public function display_cron_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $current_schedule = $this->admin->get_cron_manager()->get_current_schedule();
        $next_run = $this->admin->get_cron_manager()->get_next_scheduled_time();
        $available_schedules = $this->admin->get_cron_manager()->get_available_schedules();
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cron-settings-display.php';
    }

    public function display_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $logs = $this->admin->get_logger()->get_logs();
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/logs-display.php';
    }
}