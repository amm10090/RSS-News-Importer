<?php
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
/**
 * Plugin Name: RSS News Importer
 * Plugin URI:  https://blog.amoze.cc/rss-news-importer
 * Description: Import news articles from RSS feeds into WordPress posts.
 * Version:     1.0.0
 * Author:      HuaYangTian
 * Author URI:  https://blog.amoze.cc/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: rss-news-importer
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'RSS_NEWS_IMPORTER_VERSION', '1.0.0' );
define( 'RSS_NEWS_IMPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSS_NEWS_IMPORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_rss_news_importer() {
    // Activation code here
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_rss_news_importer() {
    // Deactivation code here
}

register_activation_hook( __FILE__, 'activate_rss_news_importer' );
register_deactivation_hook( __FILE__, 'deactivate_rss_news_importer' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-rss-news-importer.php';

/**
 * Begins execution of the plugin.
 */
function run_rss_news_importer() {
    $plugin = new RSS_News_Importer();
    $plugin->run();
}
// Add update checker
public function setup_updater() {
    if (file_exists(__DIR__ . '/plugin-update-checker/plugin-update-checker.php')) {
        require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

        $myUpdateChecker = PucFactory::buildUpdateChecker(
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
}

run_rss_news_importer();