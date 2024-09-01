<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer {

    // 插件加载器
    protected $loader;

    // 插件唯一标识符
    protected $plugin_name;

    // 插件版本
    protected $version;

    /**
     * 定义插件的核心功能
     *
     * 设置插件名称和版本，加载依赖项，定义区域设置，设置管理区域和公共区域的钩子
     *
     * @since    1.0.0
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
        $this->check_and_create_tables();

    }

//检查并创建表
    public function check_and_create_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rss_news_importer_logs';
    
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            feed_url varchar(255) NOT NULL,
            import_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
    /**
     * 加载插件所需的依赖项
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // 加载必要的类文件
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-i18n.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-menu.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-dashboard-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-settings.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-rss-news-importer-public.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-post-importer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-cron-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-logger.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-queue.php';

        $this->loader = new RSS_News_Importer_Loader();
    }

    /**
     * 为插件定义国际化功能
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new RSS_News_Importer_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * 注册与插件管理区域功能相关的所有钩子
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new RSS_News_Importer_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_menu = new RSS_News_Importer_Menu($this->get_plugin_name(), $this->get_version(), $plugin_admin);
        $plugin_settings = new RSS_News_Importer_Settings($this->get_plugin_name(), $this->get_version(), 'rss_news_importer_options', $plugin_admin);

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_menu, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_settings, 'register_settings');

        // 添加定时任务相关的钩子
        $cron_manager = new RSS_News_Importer_Cron_Manager($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action($cron_manager->get_cron_hook(), $cron_manager, 'run_tasks');
        $this->loader->add_action('admin_init', $plugin_admin, 'handle_cron_settings_save');

        // 添加AJAX处理方法
        $this->loader->add_action('wp_ajax_rss_news_importer_import_now', $plugin_admin, 'import_now_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_add_feed', $plugin_admin, 'add_feed_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_remove_feed', $plugin_admin, 'remove_feed_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_update_feed_order', $plugin_admin, 'update_feed_order_ajax');
        $this->loader->add_action('wp_ajax_rss_news_importer_run_task', $plugin_admin, 'run_task_ajax');
    }

    /**
     * 注册与插件公共面向功能相关的所有钩子
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new RSS_News_Importer_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * 运行加载器以执行所有与WordPress的钩子
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * 获取插件名称
     *
     * @since     1.0.0
     * @return    string    插件名称。
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * 获取插件加载器的引用
     *
     * @since     1.0.0
     * @return    RSS_News_Importer_Loader    协调插件钩子的加载器。
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * 获取插件版本号
     *
     * @since     1.0.0
     * @return    string    插件版本号。
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * 激活插件
     *
     * @since    1.0.0
     */
    public static function activate() {
        $cron_manager = new RSS_News_Importer_Cron_Manager('rss-news-importer', RSS_NEWS_IMPORTER_VERSION);
        $cron_manager->schedule_import();
    }

    /**
     * 停用插件
     *
     * @since    1.0.0
     */
    public static function deactivate() {
            global $wpdb;
    $table_name = $wpdb->prefix . 'rss_news_importer_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        feed_url varchar(255) NOT NULL,
        import_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
        $cron_manager = new RSS_News_Importer_Cron_Manager('rss-news-importer', RSS_NEWS_IMPORTER_VERSION);
        $cron_manager->unschedule_import();
    }

    /**
     * 卸载插件
     *
     * @since    1.0.0
     */
    public static function uninstall() {
        delete_option('rss_news_importer_options');
        // 可以在这里添加其他清理操作
    }
}