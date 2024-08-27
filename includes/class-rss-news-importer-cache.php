<?php

/**
 * The caching functionality of the plugin.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.3.1
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Cache {

    /**
     * The ID of this plugin.
     *
     * @since    1.3.1
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.3.1
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * The default cache expiration time in seconds.
     *
     * @since    1.3.1
     * @access   private
     * @var      int    $cache_expiration    The cache expiration time.
     */
    private $cache_expiration;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.1
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cache_expiration = 3600; // Default to 1 hour
    }

    // 获取缓存的 RSS 源数据
    public function get_cached_feed($url) {
        $cache_key = 'rss_feed_' . md5($url);
        return get_transient($cache_key);
    }

    // 设置缓存的 RSS 源数据
    public function set_cached_feed($url, $data, $expiration = null) {
        $cache_key = 'rss_feed_' . md5($url);
        $expiration = $expiration ?: $this->cache_expiration;
        return set_transient($cache_key, $data, $expiration);
    }

    // 删除缓存的 RSS 源数据
    public function delete_cached_feed($url) {
        $cache_key = 'rss_feed_' . md5($url);
        return delete_transient($cache_key);
    }

    // 设置缓存过期时间
    public function set_cache_expiration($expiration) {
        $this->cache_expiration = intval($expiration);
    }

    // 获取缓存过期时间
    public function get_cache_expiration() {
        return $this->cache_expiration;
    }

    // 清除所有缓存的 RSS 源数据
    public function clear_all_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_rss_feed_%' OR option_name LIKE '_transient_timeout_rss_feed_%'");
    }

    // 获取所有缓存的 RSS 源键
    public function get_all_cached_feed_keys() {
        global $wpdb;
        $keys = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_rss_feed_%' AND option_name NOT LIKE '_transient_timeout_rss_feed_%'");
        return array_map(function($key) {
            return str_replace('_transient_', '', $key);
        }, $keys);
    }
}