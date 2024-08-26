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
 * Version:           1.2.6
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            HuaYangTian
 * Author URI:        https://blog.amoze.cc/
 * Text Domain:       rss-news-importer
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常
define('RSS_NEWS_IMPORTER_VERSION', '1.2.6');
define('RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 检查PHP和WordPress版本要求
 */
function rss_news_importer_check_requirements() {
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');
    $php_min_version = '7.2';
    $wp_min_version = '5.2';

    $requirements_met = true;

    if (version_compare($php_version, $php_min_version, '<')) {
        add_action('admin_notices', function() use ($php_version, $php_min_version) {
            echo '<div class="error"><p>' . sprintf(__('RSS News Importer requires PHP version %s or higher. Your current version is %s.', 'rss-news-importer'), $php_min_version, $php_version) . '</p></div>';
        });
        $requirements_met = false;
    }

    if (version_compare($wp_version, $wp_min_version, '<')) {
        add_action('admin_notices', function() use ($wp_version, $wp_min_version) {
            echo '<div class="error"><p>' . sprintf(__('RSS News Importer requires WordPress version %s or higher. Your current version is %s.', 'rss-news-importer'), $wp_min_version, $wp_version) . '</p></div>';
        });
        $requirements_met = false;
    }

    return $requirements_met;
}

/**
 * 插件主要功能类
 */
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-news-importer.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'admin/class-rss-news-importer-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rss-parser.php';

/**
 * 开始执行插件
 */
function run_rss_news_importer() {
    if (!rss_news_importer_check_requirements()) {
        return;
    }

    // 加载文本域支持多语言
    load_plugin_textdomain('rss-news-importer', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    if (class_exists('RSS_News_Importer')) {
        $plugin = new RSS_News_Importer();
        $plugin_admin = new RSS_News_Importer_Admin($plugin->get_plugin_name(), $plugin->get_version());
    
        // 注册管理菜单
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        
        // 注册设置
        add_action('admin_init', array($plugin_admin, 'register_settings'));

        // 注册样式和脚本加载函数
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
        
        // AJAX 处理
        add_action('wp_ajax_rss_news_importer_import_now', array($plugin_admin, 'import_now_ajax'));
        add_action('wp_ajax_rss_news_importer_add_feed', array($plugin_admin, 'add_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_remove_feed', array($plugin_admin, 'remove_feed_ajax'));

        // 注册激活、停用和卸载钩子
        register_activation_hook(__FILE__, array($plugin, 'activate'));
        register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));
        register_uninstall_hook(__FILE__, array('RSS_News_Importer', 'uninstall'));

        // 注册定时任务钩子
        add_action('rss_news_importer_cron_hook', array($plugin, 'run_importer'));

        // 添加导入日志页面
        add_action('admin_menu', function() use ($plugin_admin) {
            add_submenu_page(
                'tools.php',
                __('RSS News Importer Log', 'rss-news-importer'),
                __('RSS News Importer Log', 'rss-news-importer'),
                'manage_options',
                'rss-news-importer-log',
                array($plugin_admin, 'display_import_log')
            );
        });
        
        $plugin->run();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('RSS News Importer Error: Core class not found.', 'rss-news-importer') . '</p></div>';
        });
    }
}

// 初始化插件
add_action('plugins_loaded', 'run_rss_news_importer');

/**
 * 设置插件更新检查器
 */
function setup_rss_news_importer_updater() {
    if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
        require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
        
        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/amm10090/RSS-News-Importer',
            __FILE__,
            'RSS News Importer'
        );

        // 设置包含稳定版本的分支
        $myUpdateChecker->setBranch('main');

        // 启用发布资源
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();
    }
}

// 初始化插件更新检查器
add_action('plugins_loaded', 'setup_rss_news_importer_updater');