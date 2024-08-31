<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Cache
{
    private $plugin_name;
    private $version;
    private $cache_expiration;
    private $logger;

    /**
     * 初始化缓存类
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->cache_expiration = 3600; // 默认缓存时间为1小时
        $this->logger = new RSS_News_Importer_Logger();
    }

    /**
     * 获取缓存的 RSS 源数据
     *
     * @param string $url RSS源URL
     * @return mixed|false 缓存的数据，如果没有缓存则返回false
     */
    public function get_cached_feed($url)
    {
        $cache_key = 'rss_feed_' . md5($url);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            $this->logger->log("Retrieved cached data for feed: $url", 'info');
        } else {
            $this->logger->log("No cached data found for feed: $url", 'info');
        }

        return $cached_data;
    }

    /**
     * 设置缓存的 RSS 源数据
     *
     * @param string $url RSS源URL
     * @param mixed $data 要缓存的数据
     * @param int|null $expiration 可选的过期时间（秒）
     * @return bool 是否成功设置缓存
     */
    public function set_cached_feed($url, $data, $expiration = null)
    {
        $cache_key = 'rss_feed_' . md5($url);
        $expiration = $expiration ?: $this->cache_expiration;
        $result = set_transient($cache_key, $data, $expiration);

        if ($result) {
            $this->logger->log("Cached data set for feed: $url", 'info');
        } else {
            $this->logger->log("Failed to set cache for feed: $url", 'error');
        }

        return $result;
    }

    /**
     * 删除缓存的 RSS 源数据
     *
     * @param string $url RSS源URL
     * @return bool 是否成功删除缓存
     */
    public function delete_cached_feed($url)
    {
        $cache_key = 'rss_feed_' . md5($url);
        $result = delete_transient($cache_key);

        if ($result) {
            $this->logger->log("Deleted cache for feed: $url", 'info');
        } else {
            $this->logger->log("Failed to delete cache for feed: $url", 'warning');
        }

        return $result;
    }

    /**
     * 设置缓存过期时间
     *
     * @param int $expiration 过期时间（秒）
     */
    public function set_cache_expiration($expiration)
    {
        $this->cache_expiration = intval($expiration);
        $this->logger->log("Set cache expiration to $expiration seconds", 'info');
    }

    /**
     * 获取缓存过期时间
     *
     * @return int 当前的缓存过期时间（秒）
     */
    public function get_cache_expiration()
    {
        return $this->cache_expiration;
    }

    /**
     * 清除所有缓存的 RSS 源数据
     */
    public function clear_all_cache()
    {
        global $wpdb;
        $count = $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_rss_feed_%' OR option_name LIKE '_transient_timeout_rss_feed_%'");
        $this->logger->log("Cleared all RSS feed caches. $count entries removed.", 'info');
    }

    /**
     * 获取所有缓存的 RSS 源键
     *
     * @return array 所有缓存的RSS源键
     */
    public function get_all_cached_feed_keys()
    {
        global $wpdb;
        $keys = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_rss_feed_%' AND option_name NOT LIKE '_transient_timeout_rss_feed_%'");
        $keys = array_map(function($key) {
            return str_replace('_transient_', '', $key);
        }, $keys);

        $this->logger->log("Retrieved " . count($keys) . " cached feed keys", 'info');
        return $keys;
    }

    /**
     * 检查缓存健康状态
     *
     * @return array 缓存健康状态报告
     */
    public function check_cache_health()
    {
        $keys = $this->get_all_cached_feed_keys();
        $total_size = 0;
        $expired_count = 0;
        $current_time = time();

        foreach ($keys as $key) {
            $data = get_transient($key);
            if ($data !== false) {
                $total_size += strlen(serialize($data));
            }
            
            $expiration = get_option("_transient_timeout_$key");
            if ($expiration && $expiration < $current_time) {
                $expired_count++;
            }
        }

        $report = array(
            'total_caches' => count($keys),
            'total_size' => size_format($total_size, 2),
            'expired_caches' => $expired_count
        );

        $this->logger->log("Cache health check completed. Total caches: {$report['total_caches']}, Total size: {$report['total_size']}, Expired caches: {$report['expired_caches']}", 'info');

        return $report;
    }
}