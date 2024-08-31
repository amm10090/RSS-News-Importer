<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Queue {

    private $queue_option_name = 'rss_news_importer_queue';
    private $logger;
    private $importer;

    /**
     * 构造函数
     *
     * @param RSS_News_Importer_Logger $logger 日志记录器实例
     * @param RSS_News_Importer_Post_Importer $importer 导入器实例
     */
    public function __construct(RSS_News_Importer_Logger $logger, RSS_News_Importer_Post_Importer $importer) {
        $this->logger = $logger;
        $this->importer = $importer;
    }

    /**
     * 将 RSS 源 URL 添加到队列中
     *
     * @param string $feed_url RSS 源 URL
     * @return bool 是否成功添加到队列
     */
    public function add_to_queue($feed_url) {
        $queue = $this->get_queue();
        if (!in_array($feed_url, $queue)) {
            $queue[] = $feed_url;
            $result = update_option($this->queue_option_name, $queue);
            if ($result) {
                $this->logger->log("Added feed to queue: $feed_url", 'info');
                return true;
            } else {
                $this->logger->log("Failed to add feed to queue: $feed_url", 'error');
                return false;
            }
        }
        $this->logger->log("Feed already in queue: $feed_url", 'info');
        return false;
    }
    
    /**
     * 获取当前队列
     *
     * @return array 当前队列中的 RSS 源 URL 数组
     */
    public function get_queue() {
        return get_option($this->queue_option_name, array());
    }
    
    /**
     * 从队列中移除指定的 RSS 源 URL
     *
     * @param string $feed_url 要移除的 RSS 源 URL
     * @return bool 是否成功移除
     */
    public function remove_from_queue($feed_url) {
        $queue = $this->get_queue();
        $queue = array_diff($queue, array($feed_url));
        $result = update_option($this->queue_option_name, $queue);
        if ($result) {
            $this->logger->log("Removed feed from queue: $feed_url", 'info');
            return true;
        } else {
            $this->logger->log("Failed to remove feed from queue: $feed_url", 'error');
            return false;
        }
    }
    
    /**
     * 处理队列中的所有 RSS 源 URL
     *
     * @return array 处理结果数组
     */
    public function process_queue() {
        $queue = $this->get_queue();
        $results = array();

        foreach ($queue as $feed_url) {
            $this->logger->log("Processing feed from queue: $feed_url", 'info');
            try {
                $imported_count = $this->importer->import_feed($feed_url);
                $results[$feed_url] = array(
                    'status' => 'success',
                    'imported_count' => $imported_count
                );
                $this->remove_from_queue($feed_url);
            } catch (Exception $e) {
                $this->logger->log("Error processing feed: $feed_url. Error: " . $e->getMessage(), 'error');
                $results[$feed_url] = array(
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * 清空队列
     *
     * @return bool 是否成功清空队列
     */
    public function clear_queue() {
        $result = delete_option($this->queue_option_name);
        if ($result) {
            $this->logger->log("Queue cleared successfully", 'info');
            return true;
        } else {
            $this->logger->log("Failed to clear queue", 'error');
            return false;
        }
    }

    /**
     * 获取队列长度
     *
     * @return int 队列中的项目数量
     */
    public function get_queue_length() {
        return count($this->get_queue());
    }

    /**
     * 检查队列是否为空
     *
     * @return bool 队列是否为空
     */
    public function is_queue_empty() {
        return $this->get_queue_length() === 0;
    }
}