<?php
/**
 * RSS News Importer
 *
 * @package           RSSNewsImporter
 * @author            HuaYangTian
 * @copyright         2024 HuaYangTian
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       RSS News Importer
 * Plugin URI:        https://blog.amoze.cc/rss-news-importer
 * Description:       Import news articles from RSS feeds into WordPress posts.
 * Version:           1.1.7
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            HuaYangTian
 * Author URI:        https://blog.amoze.cc/
 * Text Domain:       rss-news-importer
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('RSS_NEWS_IMPORTER_VERSION', '1.1.7');
define('RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path(__FILE__) . 'includes/class-rss-news-importer.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-rss-news-importer-admin.php';

/**
 * Begins execution of the plugin.
 */
function run_rss_news_importer() {
    if (class_exists('RSS_News_Importer')) {
        $plugin = new RSS_News_Importer();
        $plugin_admin = new RSS_News_Importer_Admin($plugin->get_plugin_name(), $plugin->get_version());
    
        // 注册样式和脚本加载函数
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
        
        // 其他管理页面相关的钩子
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        add_action('admin_init', array($plugin_admin, 'register_settings'));

        // 注册激活钩子
        register_activation_hook(__FILE__, array($plugin, 'activate'));
        // 注册停用钩子
        register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));
        // 注册卸载钩子
        register_uninstall_hook(__FILE__, array('RSS_News_Importer', 'uninstall'));

        // 注册定时任务钩子
        add_action('rss_news_importer_cron_hook', array($plugin, 'run_importer'));

        $plugin->run();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>RSS News Importer Error: Core class not found.</p></div>';
        });
    }
}

// 初始化插件
add_action('plugins_loaded', 'run_rss_news_importer');

/**
 * Setup plugin updater
 */
function setup_rss_news_importer_updater() {
    if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
        require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
        
        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/amm10090/RSS-News-Importer',
            __FILE__,
            'RSS News Importer'
        );

        // Set the branch that contains the stable release.
        $myUpdateChecker->setBranch('main');

        // Enable release assets
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();
    }
}

// 初始化插件更新检查器
add_action('plugins_loaded', 'setup_rss_news_importer_updater');