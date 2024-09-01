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

    /**
     * The option name for storing the queue in the database.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $queue_option_name    The name of the option that stores the queue.
     */
    private $queue_option_name = 'rss_news_importer_queue';

    /**
     * The maximum number of items allowed in the queue.
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $max_queue_size    The maximum number of items in the queue.
     */
    private $max_queue_size = 100;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // 如果需要在构造函数中初始化任何内容，可以在这里添加
    }

    /**
     * Add an RSS feed URL to the queue.
     *
     * @since    1.0.0
     * @param    string    $feed_url    The URL of the RSS feed to add to the queue.
     * @return   bool      True if the feed was added, false if it was already in the queue or the queue is full.
     */
    public function add_to_queue($feed_url) {
        $queue = $this->get_queue();
        if (!in_array($feed_url, $queue) && count($queue) < $this->max_queue_size) {
            $queue[] = $feed_url;
            update_option($this->queue_option_name, $queue);
            return true;
        }
        return false;
    }
    
    /**
     * Get the current queue.
     *
     * @since    1.0.0
     * @return   array    The current queue of RSS feed URLs.
     */
    public function get_queue() {
        return get_option($this->queue_option_name, array());
    }
    
    /**
     * Remove a specific RSS feed URL from the queue.
     *
     * @since    1.0.0
     * @param    string    $feed_url    The URL of the RSS feed to remove from the queue.
     * @return   bool      True if the feed was removed, false if it wasn't in the queue.
     */
    public function remove_from_queue($feed_url) {
        $queue = $this->get_queue();
        $key = array_search($feed_url, $queue);
        if ($key !== false) {
            unset($queue[$key]);
            update_option($this->queue_option_name, array_values($queue));
            return true;
        }
        return false;
    }
    
    /**
     * Process all RSS feed URLs in the queue.
     *
     * @since    1.0.0
     * @param    RSS_News_Importer_Post_Importer    $importer    An instance of the post importer.
     * @return   array    An array of results, with feed URLs as keys and import results as values.
     */
    public function process_queue($importer) {
        $queue = $this->get_queue();
        $results = array();

        foreach ($queue as $feed_url) {
            $result = $importer->import_feed($feed_url);
            $results[$feed_url] = $result;
            $this->remove_from_queue($feed_url);
        }

        return $results;
    }
    private function display_queue_status() {
        $queue_status = $this->dashboard_manager->get_queue_status();
        ?>
        <div class="postbox">
            <h2 class="hndle"><span><?php _e('导入队列状态', 'rss-news-importer'); ?></span></h2>
            <div class="inside">
                <p><?php _e('当前队列项目数：', 'rss-news-importer'); ?> <?php echo esc_html($queue_status['current_size']); ?></p>
                <p><?php _e('最大队列容量：', 'rss-news-importer'); ?> <?php echo esc_html($queue_status['max_size']); ?></p>
                <p><?php _e('队列容量使用：', 'rss-news-importer'); ?> <?php echo esc_html($queue_status['usage_percentage']); ?>%</p>
            </div>
        </div>
        <?php
    }

    /**
     * Clear the entire queue.
     *
     * @since    1.0.0
     * @return   bool    True if the queue was cleared, false if there was an error.
     */
    public function clear_queue() {
        return update_option($this->queue_option_name, array());
    }

    /**
     * Get the current size of the queue.
     *
     * @since    1.0.0
     * @return   int    The number of items currently in the queue.
     */
    public function get_queue_size() {
        return count($this->get_queue());
    }

    /**
     * Set the maximum size of the queue.
     *
     * @since    1.0.0
     * @param    int    $size    The new maximum size of the queue.
     */
    public function set_max_queue_size($size) {
        $this->max_queue_size = max(1, intval($size));
    }

    /**
     * Get the maximum size of the queue.
     *
     * @since    1.0.0
     * @return   int    The maximum number of items allowed in the queue.
     */
    public function get_max_queue_size() {
        return $this->max_queue_size;
    }

    /**
     * Check if the queue is full.
     *
     * @since    1.0.0
     * @return   bool    True if the queue is full, false otherwise.
     */
    public function is_queue_full() {
        return $this->get_queue_size() >= $this->max_queue_size;
    }
}