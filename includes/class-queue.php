<?php

/**
 * Manages the queue for RSS feed imports.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Queue {

    private $queue_option_name = 'rss_news_importer_queue';
    
    // 将 RSS 源 URL 添加到队列中
    public function add_to_queue($feed_url) {
        $queue = $this->get_queue();
        if (!in_array($feed_url, $queue)) {
            $queue[] = $feed_url;
            update_option($this->queue_option_name, $queue);
        }
    }
    
    // 获取当前队列
    public function get_queue() {
        return get_option($this->queue_option_name, array());
    }
    
    // 从队列中移除指定的 RSS 源 URL
    public function remove_from_queue($feed_url) {
        $queue = $this->get_queue();
        $queue = array_diff($queue, array($feed_url));
        update_option($this->queue_option_name, $queue);
    }
    
    // 处理队列中的所有 RSS 源 URL
    public function process_queue() {
        $queue = $this->get_queue();
        $importer = new RSS_News_Importer_Post_Importer();
        foreach ($queue as $feed_url) {
            $importer->import_feed($feed_url);
            $this->remove_from_queue($feed_url);
        }
    }
}