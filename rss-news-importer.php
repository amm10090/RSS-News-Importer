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
 * Version:           1.0.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            HuaYangTian
 * Author URI:        https://blog.amoze.cc/
 * Text Domain:       rss-news-importer
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/amm10090/RSS-News-Importer
 */

// 如果直接访问此文件，则中止执行。
if ( ! defined( 'WPINC' ) ) {
    die;
}

// 定义插件常量。
define( 'RSS_NEWS_IMPORTER_VERSION', '1.0.3' );
define( 'RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * 在插件激活时执行的代码。
 *
 * @since 1.0.0
 */
function activate_rss_news_importer() {
    // 激活代码在此处。
}

/**
 * 在插件停用时执行的代码。
 *
 * @since 1.0.0
 */
function deactivate_rss_news_importer() {
    // 停用代码在此处。
}

register_activation_hook( __FILE__, 'activate_rss_news_importer' );
register_deactivation_hook( __FILE__, 'deactivate_rss_news_importer' );

/**
 * 包含插件的核心类文件。
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-rss-news-importer.php';

/**
 * 设置插件更新检查器。
 *
 * @since 1.0.0
 */
function setup_rss_news_importer_updater() {
    // 确保 Plugin Update Checker 库存在
    if ( file_exists( plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
        use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

        $myUpdateChecker = PucFactory::buildUpdateChecker(
            'https://github.com/amm10090/RSS-News-Importer/',
            __FILE__,
            'rss-news-importer'
        );

        // 设置包含稳定版本的分支
        $myUpdateChecker->setBranch('main');

        // 启用发布资产
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();

        // 如果使用私有仓库，请取消注释下面的行并添加您的访问令牌
        // $myUpdateChecker->setAuthentication('your-token-here');
    }
}

/**
 * 开始执行插件。
 *
 * @since 1.0.0
 */
function run_rss_news_importer() {
    $plugin = new RSS_News_Importer();
    $plugin->run();

    // 设置更新检查器
    setup_rss_news_importer_updater();
}

run_rss_news_importer();