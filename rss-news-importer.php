<?php
    use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

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
 * Version:           1.5.0
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
define('RSS_NEWS_IMPORTER_VERSION', '1.5.0');
define('RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载主插件类
require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-news-importer.php';

/**
 * 检查PHP和WordPress版本要求
 *
 * @return bool 是否满足要求
 */
function rss_news_importer_check_requirements()
{
    // 获取当前PHP版本
    $php_version = phpversion();
    // 获取当前WordPress版本
    $wp_version = get_bloginfo('version');
    // 设置最低PHP版本要求
    $php_min_version = '7.2';
    // 设置最低WordPress版本要求
    $wp_min_version = '5.2';

    // 默认要求已满足
    $requirements_met = true;

    // 检查PHP版本
    if (version_compare($php_version, $php_min_version, '<')) {
        // 如果PHP版本不满足要求，添加一个管理通知
        add_action('admin_notices', function () use ($php_version, $php_min_version) {
            echo '<div class="error"><p>' . sprintf(__('RSS News Importer requires PHP version %s or higher. Your current version is %s.', 'rss-news-importer'), $php_min_version, $php_version) . '</p></div>';
        });
        $requirements_met = false;
    }

    // 检查WordPress版本
    if (version_compare($wp_version, $wp_min_version, '<')) {
        // 如果WordPress版本不满足要求，添加一个管理通知
        add_action('admin_notices', function () use ($wp_version, $wp_min_version) {
            echo '<div class="error"><p>' . sprintf(__('RSS News Importer requires WordPress version %s or higher. Your current version is %s.', 'rss-news-importer'), $wp_min_version, $wp_version) . '</p></div>';
        });
        $requirements_met = false;
    }

    // 返回是否满足所有要求
    return $requirements_met;
}

/**
 * 初始化插件
 */
function run_rss_news_importer()
{
    // 检查是否满足版本要求
    if (!rss_news_importer_check_requirements()) {
        return;
    }

    // 创建主插件类的实例
    $plugin = new RSS_News_Importer();
    // 运行插件
    $plugin->run();
}

// 在 WordPress 初始化时运行插件
add_action('plugins_loaded', 'run_rss_news_importer');

/**
 * 插件激活时的操作
 */
function activate_rss_news_importer()
{
    // 加载激活器类
    require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-news-importer-activator.php';
    // 调用激活方法
    RSS_News_Importer_Activator::activate();
}

/**
 * 插件停用时的操作
 */
function deactivate_rss_news_importer()
{
    // 加载停用器类
    require_once RSS_NEWS_IMPORTER_PLUGIN_DIR . 'includes/class-rss-news-importer-deactivator.php';
    // 调用停用方法
    RSS_News_Importer_Deactivator::deactivate();
}

// 注册激活钩子
register_activation_hook(__FILE__, 'activate_rss_news_importer');
// 注册停用钩子
register_deactivation_hook(__FILE__, 'deactivate_rss_news_importer');

// 设置插件更新检查器
$plugin_update_checker_file = RSS_NEWS_IMPORTER_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
if (file_exists($plugin_update_checker_file)) {
    require_once $plugin_update_checker_file;

    // 创建更新检查器实例
    $myUpdateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/amm10090/RSS-News-Importer', // GitHub仓库URL
        __FILE__, // 当前插件文件的完整路径
        'RSS News Importer' // 插件slug
    );

    // 设置包含稳定版本的分支
    $myUpdateChecker->setBranch('main');

    // 启用发布资源
    $myUpdateChecker->getVcsApi()->enableReleaseAssets();
} else {
    // 如果插件更新检查器文件不存在，添加一个管理通知
    add_action('admin_notices', function() {
        echo '<div class="error"><p>' . __('Plugin Update Checker is missing. RSS News Importer may not be able to receive updates automatically.', 'rss-news-importer') . '</p></div>';
    });
}