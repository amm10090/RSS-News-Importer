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

    protected $loader;
    protected $plugin_name;
    protected $version;

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
    }

    private function load_dependencies() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-loader.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-i18n.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-rss-news-importer-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-rss-news-importer-public.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-post-importer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-cron-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-logger.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-queue.php';

        $this->loader = new RSS_News_Importer_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new RSS_News_Importer_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        $plugin_admin = new RSS_News_Importer_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }

    private function define_public_hooks() {
        $plugin_public = new RSS_News_Importer_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    public function activate() {
        $cron_manager = new RSS_News_Importer_Cron_Manager($this->get_plugin_name(), $this->get_version());
        $cron_manager->schedule_import();
    }

    public function deactivate() {
        $cron_manager = new RSS_News_Importer_Cron_Manager($this->get_plugin_name(), $this->get_version());
        $cron_manager->unschedule_import();
    }

    public static function uninstall() {
        delete_option('rss_news_importer_options');
        // 可以在这里添加其他清理操作
    }

    public function run_importer() {
        $importer = new RSS_News_Importer_Post_Importer($this->get_plugin_name(), $this->get_version());
        $importer->import_feeds();
    }
}