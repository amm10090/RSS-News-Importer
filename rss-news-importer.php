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
 * Version:           1.6.0
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

// 定义插件常量
define('RSS_NEWS_IMPORTER_VERSION', '1.6.0');
define('RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载必要的类文件
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-news-importer.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'admin/class-rss-news-importer-admin.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-parser.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-news-importer-cache.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-post-importer.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-cron-manager.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-logger.php';
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-queue.php';

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
        add_action('admin_notices', function () use ($php_version, $php_min_version) {
            echo '<div class="error"><p>' . sprintf(__('RSS News Importer requires PHP version %s or higher. Your current version is %s.', 'rss-news-importer'), $php_min_version, $php_version) . '</p></div>';
        });
        $requirements_met = false;
    }

    if (version_compare($wp_version, $wp_min_version, '<')) {
        add_action('admin_notices', function () use ($wp_version, $wp_min_version) {
            echo '<div class="error"><p>' . sprintf(__('RSS News Importer requires WordPress version %s or higher. Your current version is %s.', 'rss-news-importer'), $wp_min_version, $wp_version) . '</p></div>';
        });
        $requirements_met = false;
    }

    return $requirements_met;
}

/**
 * 开始执行插件
 */
function run_rss_news_importer() {
    if (!rss_news_importer_check_requirements()) {
        return;
    }

    // 加载文本域支持多语言
    load_plugin_textdomain('rss-news-importer', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // 初始化主插件类
    $plugin = new RSS_News_Importer();

    // 注册激活、停用和卸载钩子
    register_activation_hook(__FILE__, array('RSS_News_Importer', 'activate'));
    register_deactivation_hook(__FILE__, array('RSS_News_Importer', 'deactivate'));
    register_uninstall_hook(__FILE__, array('RSS_News_Importer', 'uninstall'));

    $plugin->run();
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