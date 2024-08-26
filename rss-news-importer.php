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
 * Version:           1.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            HuaYangTian
 * Author URI:        https://blog.amoze.cc/
 * Text Domain:       rss-news-importer
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'RSS_NEWS_IMPORTER_VERSION', '1.1.0' );
define( 'RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
$class_file = plugin_dir_path( __FILE__ ) . 'includes/class-rss-news-importer.php';
if ( file_exists( $class_file ) ) {
    require_once $class_file;
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p>RSS News Importer Error: Core class file not found.</p></div>';
    });
}

/**
 * Setup plugin updater
 */
function setup_rss_news_importer_updater() {
    if ( file_exists( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
        require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
        
        // 使用完全限定名称而不是 use 语句
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

/**
 * Begins execution of the plugin.
 */
function run_rss_news_importer() {
    if ( class_exists( 'RSS_News_Importer' ) ) {
        $plugin = new RSS_News_Importer();
        $plugin->run();
    } else {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p>RSS News Importer Error: Core class not found.</p></div>';
        });
    }
}

// Initialize plugin
add_action( 'plugins_loaded', 'setup_rss_news_importer_updater' );
add_action( 'plugins_loaded', 'run_rss_news_importer' );