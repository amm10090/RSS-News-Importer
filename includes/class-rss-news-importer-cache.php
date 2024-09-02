<?php

if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Cache
{
    private $cache_group;
    private $version;
    private $logger;
    private $expiration;

    /**
     * 构造函数
     * 
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     * @param RSS_News_Importer_Logger $logger 日志记录器实例
     */
    public function __construct($plugin_name, $version, $logger)
    {
        $this->cache_group = $plugin_name . '_cache';
        $this->version = get_option($plugin_name . '_cache_version', '1.0');
        $this->logger = $logger;
        $this->expiration = 3600; // 默认缓存时间为1小时
    }

    /**
     * 获取缓存的数据
     * 
     * @param string $key 缓存键
     * @return mixed 缓存的数据或false（如果缓存不存在）
     */
    public function get($key)
    {
        $versioned_key = $this->get_versioned_key($key);
        $data = wp_cache_get($versioned_key, $this->cache_group);

        if ($data === false) {
            $this->logger->log("Cache miss for key: $key", 'debug');
            return false;
        }

        $this->logger->log("Cache hit for key: $key", 'debug');
        return maybe_unserialize($data);
    }

    /**
     * 设置缓存数据
     * 
     * @param string $key 缓存键
     * @param mixed $data 要缓存的数据
     * @param int $expiration 过期时间（秒）
     * @return bool 是否成功设置缓存
     */
    public function set($key, $data, $expiration = null)
    {
        $versioned_key = $this->get_versioned_key($key);
        $expiration = $expiration ?? $this->expiration;
        $serialized_data = maybe_serialize($data);

        $result = wp_cache_set($versioned_key, $serialized_data, $this->cache_group, $expiration);

        if ($result) {
            $this->logger->log("Cache set for key: $key", 'debug');
        } else {
            $this->logger->log("Failed to set cache for key: $key", 'warning');
        }

        return $result;
    }

    /**
     * 删除特定的缓存
     * 
     * @param string $key 缓存键
     * @return bool 是否成功删除缓存
     */
    public function delete($key)
    {
        $versioned_key = $this->get_versioned_key($key);
        $result = wp_cache_delete($versioned_key, $this->cache_group);

        if ($result) {
            $this->logger->log("Cache deleted for key: $key", 'debug');
        } else {
            $this->logger->log("Failed to delete cache for key: $key", 'warning');
        }

        return $result;
    }

    /**
     * 清除所有缓存
     * 
     * @return bool 是否成功清除所有缓存
     */
    public function clear_all_cache()
    {
        $this->logger->log('Clearing all cache', 'info');

        // 增加缓存版本号来使所有缓存失效
        $this->increment_cache_version();

        // 清除WordPress对象缓存
        $wp_cache_flushed = wp_cache_flush();

        // 清除与插件相关的transients
        $transients_cleared = $this->clear_plugin_transients();

        $all_cleared = $wp_cache_flushed && $transients_cleared;

        if ($all_cleared) {
            $this->logger->log('All cache cleared successfully', 'info');
        } else {
            $this->logger->log('Some cache clearing operations failed', 'warning');
        }

        return $all_cleared;
    }

    /**
     * 获取缓存使用情况
     * 
     * @return string 缓存使用百分比
     */
    public function get_cache_usage()
    {
        $cache_size = $this->get_cache_size();
        $max_cache_size = $this->get_max_cache_size();
        $usage_percentage = ($cache_size / $max_cache_size) * 100;
        return round($usage_percentage, 2) . '%';
    }

    /**
     * 设置缓存过期时间
     * 
     * @param int $seconds 过期时间（秒）
     */
    public function set_expiration($seconds)
    {
        $this->expiration = $seconds;
    }

    /**
     * 获取当前缓存过期时间
     * 
     * @return int 当前缓存过期时间（秒）
     */
    public function get_expiration()
    {
        return $this->expiration;
    }

    /**
     * 检查缓存是否已满
     * 
     * @return bool 缓存是否已满
     */
    public function is_cache_full()
    {
        $usage = $this->get_cache_usage();
        return (float)$usage >= 90.0; // 如果使用率超过90%，认为缓存已满
    }

    /**
     * 获取所有缓存的键
     * 
     * @return array 所有缓存的键
     */
    public function get_all_cache_keys()
    {
        global $wp_object_cache;

        if (is_object($wp_object_cache) && isset($wp_object_cache->cache[$this->cache_group])) {
            return array_keys($wp_object_cache->cache[$this->cache_group]);
        }

        return array();
    }

    /**
     * 缓存预热
     * 
     * @param array $urls 需要预热的RSS源URL列表
     */
    public function warm_cache($urls)
    {
        foreach ($urls as $url) {
            $key = 'rss_feed_' . md5($url);
            if (!$this->get($key)) {
                // 这里应该调用RSS解析器来获取数据，但为了避免循环依赖，我们只记录日志
                $this->logger->log("Warming cache for URL: $url", 'info');
            }
        }
    }

    /**
     * 生成带版本的缓存键
     * 
     * @param string $key 原始缓存键
     * @return string 带版本的缓存键
     */
    private function get_versioned_key($key)
    {
        return $this->version . '_' . $key;
    }

    /**
     * 增加缓存版本
     */
    private function increment_cache_version()
    {
        $this->version = floatval($this->version) + 0.1;
        $this->version = number_format($this->version, 1);
        update_option($this->cache_group . '_version', $this->version);
        $this->logger->log("Cache version incremented to: {$this->version}", 'info');
    }

    /**
     * 清除插件相关的transients
     * 
     * @return bool 是否成功清除所有相关transients
     */
    private function clear_plugin_transients()
    {
        global $wpdb;
        $plugin_transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_' . $this->cache_group . '_') . '%',
                $wpdb->esc_like('_transient_timeout_' . $this->cache_group . '_') . '%'
            )
        );

        $all_cleared = true;
        foreach ($plugin_transients as $transient) {
            if (!delete_option($transient)) {
                $all_cleared = false;
                $this->logger->log("Failed to delete transient: $transient", 'warning');
            }
        }

        return $all_cleared;
    }

    /**
     * 获取当前缓存大小
     * 
     * @return int 当前缓存大小（字节）
     */
    private function get_cache_size()
    {
        global $wp_object_cache;

        if (is_object($wp_object_cache) && isset($wp_object_cache->cache[$this->cache_group])) {
            return strlen(serialize($wp_object_cache->cache[$this->cache_group]));
        }

        return 0;
    }

    /**
     * 获取最大缓存大小
     * 
     * @return int 最大缓存大小（字节）
     */
    private function get_max_cache_size()
    {
        // 这里返回一个固定值，您可以根据需要调整或从配置中读取
        return 10 * 1024 * 1024; // 10MB
    }

    public function get_feed_last_update($feed_url)
    {
        $cache_key = 'feed_last_update_' . md5($feed_url);
        return $this->get($cache_key);
    }

    public function set_feed_last_update($feed_url, $timestamp)
    {
        $cache_key = 'feed_last_update_' . md5($feed_url);
        return $this->set($cache_key, $timestamp);
    }
}
