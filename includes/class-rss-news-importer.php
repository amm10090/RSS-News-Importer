<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer {

    // 加载器实例
    protected $loader;
    
    // 插件名称
    protected $plugin_name;
    
    // 插件版本
    protected $version;
    
    // 管理类实例
    protected $admin;
    
    // 公共类实例
    protected $public;
    
    // 导入器实例
    protected $importer;
    
    // 定时任务管理器实例
    protected $cron_manager;
    
    // 日志记录器实例
    protected $logger;
    
    // 缓存管理器实例
    protected $cache;
    
    // 国际化实例
    protected $i18n;
    
    // 仪表板实例
    protected $dashboard;

    /**
     * 定义核心功能、设置国际化并加载管理和公共类。
     */
    public function __construct() {
        if (defined('RSS_NEWS_IMPORTER_VERSION')) {
            $this->version = RSS_NEWS_IMPORTER_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'rss-news-importer';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->setup_cron_jobs();
    }

    /**
     * 加载插件依赖项
     */
    private function load_dependencies() {
        // 加载加载器类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-loader.php';
        // 加载国际化类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-i18n.php';
        // 加载管理类
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin.php';
        // 加载公共类
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-rss-news-importer-public.php';
        // 加载文章导入器类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-post-importer.php';
        // 加载定时任务管理器类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-cron-manager.php';
        // 加载日志记录器类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-logger.php';
        // 加载缓存管理器类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-cache.php';
        // 加载仪表板类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-dashboard.php';
       // 加载解析器类
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-parser.php';
        // 创建加载器实例
        $this->loader = new RSS_News_Importer_Loader();
        // 创建国际化实例
        $this->i18n = new RSS_News_Importer_i18n();
        // 创建日志记录器实例
        $this->logger = new RSS_News_Importer_Logger();
        // 创建缓存管理器实例
        $this->cache = new RSS_News_Importer_Cache($this->get_plugin_name(), $this->get_version());
        // 创建仪表板实例
        $this->dashboard = new RSS_News_Importer_Dashboard($this->get_plugin_name(), $this->get_version());
        // 创建导入器实例
        $this->importer = new RSS_News_Importer_Post_Importer($this->get_plugin_name(), $this->get_version());
        // 设置导入器和仪表板的相互引用
        $this->importer->set_dashboard($this->dashboard);
        $this->dashboard->set_importer($this->importer);
        // 创建管理类实例
        $this->admin = new RSS_News_Importer_Admin($this->get_plugin_name(), $this->get_version(), $this->dashboard, $this->importer);
        // 创建公共类实例
        $this->public = new RSS_News_Importer_Public($this->get_plugin_name(), $this->get_version());
        // 创建定时任务管理器实例
        $this->cron_manager = new RSS_News_Importer_Cron_Manager($this->get_plugin_name(), $this->get_version());
    }

    /**
     * 定义国际化功能
     */
    private function set_locale() {
        $this->loader->add_action('plugins_loaded', $this->i18n, 'load_plugin_textdomain');
    }

    /**
     * 注册所有与管理区域功能相关的钩子
     */
    private function define_admin_hooks() {
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $this->admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $this->admin, 'register_settings');

        // AJAX 处理
        $this->loader->add_action('wp_ajax_rss_news_importer_import_now', $this->admin, 'import_now_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_add_feed', $this->admin, 'add_feed_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_remove_feed', $this->admin, 'remove_feed_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_update_feed_order', $this->admin, 'update_feed_order_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_run_task', $this->admin, 'run_task_ajax');
    }

    /**
     * 注册所有与公共区域功能相关的钩子
     */
    private function define_public_hooks() {
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');
    }

    /**
     * 设置定时任务
     */
    private function setup_cron_jobs() {
        $this->loader->add_action('rss_news_importer_cron_hook', $this->cron_manager, 'run_tasks');
        $this->loader->add_action('admin_init', $this->admin, 'handle_cron_settings_save');
    }

    /**
     * 运行加载器以执行所有注册的钩子
     */
    public function run() {
        $this->loader->run();
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
     * 获取加载器对象的引用
     *
     * @return RSS_News_Importer_Loader 维护插件钩子列表的加载器对象
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * 获取插件版本号
     *
     * @return string 插件版本号
     */
    public function get_version() {
        return $this->version;
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
     * 获取缓存管理器实例
     *
     * @return RSS_News_Importer_Cache 缓存管理器实例
     */
    public function get_cache() {
        return $this->cache;
    }
}