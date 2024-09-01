<?php

if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Cache {
    private $cache_group;
    private $expiration;

    /**
     * 构造函数
     * 
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     */
    public function __construct($plugin_name, $version) {
        $this->cache_group = $plugin_name . '_' . $version;
        $this->expiration = 3600; // 默认缓存时间为1小时
    }

    /**
     * 获取缓存的RSS源数据
     * 
     * @param string $feed_url RSS源URL
     * @return mixed 缓存的数据或false（如果缓存不存在）
     */
    public function get_cached_feed($feed_url) {
        $cache_key = $this->get_cache_key($feed_url);
        return wp_cache_get($cache_key, $this->cache_group);
    }

    /**
     * 设置RSS源数据的缓存
     * 
     * @param string $feed_url RSS源URL
     * @param mixed $data 要缓存的数据
     * @return bool 缓存是否成功设置
     */
    public function set_cached_feed($feed_url, $data) {
        $cache_key = $this->get_cache_key($feed_url);
        return wp_cache_set($cache_key, $data, $this->cache_group, $this->expiration);
    }

    /**
     * 删除特定RSS源的缓存
     * 
     * @param string $feed_url RSS源URL
     * @return bool 缓存是否成功删除
     */
    public function delete_cached_feed($feed_url) {
        $cache_key = $this->get_cache_key($feed_url);
        return wp_cache_delete($cache_key, $this->cache_group);
    }

    /**
     * 清除所有缓存
     * 
     * @return bool 是否成功清除所有缓存
     */
    public function clear_all_cache() {
        return wp_cache_flush();
    }

    /**
     * 获取缓存使用情况
     * 
     * @return string 缓存使用百分比
     */
    public function get_cache_usage() {
        $cache_size = $this->get_cache_size();
        $max_cache_size = $this->get_max_cache_size();
        $usage_percentage = ($cache_size / $max_cache_size) * 100;
        return round($usage_percentage, 2) . '%';
    }

    /**
     * 获取当前缓存大小
     * 
     * @return int 当前缓存大小（字节）
     */
    private function get_cache_size() {
        global $wp_object_cache;
        
        if (is_object($wp_object_cache) && isset($wp_object_cache->cache)) {
            $cache_size = strlen(serialize($wp_object_cache->cache));
        } else {
            $cache_size = 0;
        }
        
        return $cache_size;
    }

    /**
     * 获取最大缓存大小
     * 
     * @return int 最大缓存大小（字节）
     */
    private function get_max_cache_size() {
        // 这里返回一个固定值，您可以根据需要调整
        return 10 * 1024 * 1024; // 10MB
    }

    /**
     * 生成缓存键
     * 
     * @param string $feed_url RSS源URL
     * @return string 缓存键
     */
    private function get_cache_key($feed_url) {
        return md5($feed_url);
    }

    /**
     * 设置缓存过期时间
     * 
     * @param int $seconds 过期时间（秒）
     */
    public function set_expiration($seconds) {
        $this->expiration = $seconds;
    }

    /**
     * 获取当前缓存过期时间
     * 
     * @return int 当前缓存过期时间（秒）
     */
    public function get_expiration() {
        return $this->expiration;
    }

    /**
     * 检查缓存是否已满
     * 
     * @return bool 缓存是否已满
     */
    public function is_cache_full() {
        $usage = $this->get_cache_usage();
        return (float)$usage >= 90.0; // 如果使用率超过90%，认为缓存已满
    }

    /**
     * 获取所有缓存的键
     * 
     * @return array 所有缓存的键
     */
    public function get_all_cache_keys() {
        global $wp_object_cache;
        
        if (is_object($wp_object_cache) && isset($wp_object_cache->cache[$this->cache_group])) {
            return array_keys($wp_object_cache->cache[$this->cache_group]);
        }
        
        return array();
    }
}