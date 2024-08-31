<?php

// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin
{
    // 插件名称
    private $plugin_name;

    // 插件版本
    private $version;

    // 核心管理类实例
    private $core;

    // 设置类实例
    private $settings;

    // AJAX处理类实例
    private $ajax;

    // 显示类实例
    private $display;

    // 选项名称
    private $option_name = 'rss_news_importer_options';

    // 仪表板实例
    private $dashboard;

    // 导入器实例
    private $importer;

    /**
     * 构造函数
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     * @param RSS_News_Importer_Dashboard $dashboard 仪表板实例
     * @param RSS_News_Importer_Post_Importer $importer 导入器实例
     */
    public function __construct($plugin_name, $version, RSS_News_Importer_Dashboard $dashboard, RSS_News_Importer_Post_Importer $importer)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->dashboard = $dashboard;
        $this->importer = $importer;

        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * 加载依赖
     */
    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin-core.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin-settings.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin-ajax.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin-display.php';
    }

    /**
     * 初始化组件
     */
    private function init_components()
    {
        $this->core = new RSS_News_Importer_Admin_Core($this->plugin_name, $this->version);
        $this->settings = new RSS_News_Importer_Admin_Settings($this->plugin_name, $this->version);
        $this->ajax = new RSS_News_Importer_Admin_Ajax($this->plugin_name, $this->version, $this->importer);
        $this->display = new RSS_News_Importer_Admin_Display($this->plugin_name, $this->version);
    }

    /**
     * 初始化钩子
     */
    public function init()
    {
        $this->core->init_hooks();
        $this->settings->init_hooks();
        $this->ajax->init_hooks();

        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * 添加插件管理菜单
     */
    public function add_plugin_admin_menu()
    {
        $this->core->add_plugin_admin_menu();
    }

    /**
     * 加载管理样式
     *
     * @param string $hook 当前页面的钩子后缀
     */
    public function enqueue_styles($hook)
    {
        $this->core->enqueue_styles($hook);
    }

    /**
     * 加载管理脚本
     *
     * @param string $hook 当前页面的钩子后缀
     */
    public function enqueue_scripts($hook)
    {
        $this->core->enqueue_scripts($hook);
    }

    /**
     * 显示插件设置页面
     */
    public function display_plugin_setup_page()
    {
        $this->display->display_plugin_setup_page();
    }

    /**
     * 显示仪表板页面
     */
    public function display_dashboard_page()
    {
        $this->dashboard->display_dashboard();
    }

    /**
     * 显示定时任务设置页面
     */
    public function display_cron_settings_page()
    {
        $this->display->display_cron_settings_page();
    }

    /**
     * 处理定时任务设置保存
     */
    public function handle_cron_settings_save()
    {
        $this->settings->handle_cron_settings_save();
    }

    /**
     * 注册插件设置
     */
    public function register_settings()
    {
        $this->settings->register_settings();
    }

    /**
     * AJAX: 立即导入
     */
    public function import_now_ajax()
    {
        $this->ajax->import_now_ajax();
    }

    /**
     * AJAX: 添加新的RSS源
     */
    public function add_feed_ajax()
    {
        $this->ajax->add_feed_ajax();
    }

    /**
     * AJAX: 移除RSS源
     */
    public function remove_feed_ajax()
    {
        $this->ajax->remove_feed_ajax();
    }

    /**
     * AJAX: 更新RSS源顺序
     */
    public function update_feed_order_ajax()
    {
        $this->ajax->update_feed_order_ajax();
    }

    /**
     * AJAX: 运行任务
     */
    public function run_task_ajax()
    {
        $this->ajax->run_task_ajax();
    }

    /**
     * 获取插件选项
     *
     * @return array 插件选项
     */
    public function get_options()
    {
        return get_option($this->option_name, array());
    }

    /**
     * 更新插件选项
     *
     * @param array $options 新的选项值
     * @return bool 是否成功更新
     */
    public function update_options($options)
    {
        return update_option($this->option_name, $options);
    }
}