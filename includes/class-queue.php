<?php

if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Queue
{

    private $queue_option_name = 'rss_news_importer_queue';
    private $max_queue_size = 100;
    private $logger;
    private $cache;

    // 构造函数,初始化队列
    public function __construct($logger, $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    // 添加RSS源URL到队列
    public function add_to_queue($feed_url)
    {
        $queue = $this->get_queue();
        if (!in_array($feed_url, $queue) && count($queue) < $this->max_queue_size) {
            $queue[] = $feed_url;
            $this->update_queue($queue);
            $this->logger->log("添加到队列: $feed_url", 'info');
            return true;
        }
        $this->logger->log("无法添加到队列: $feed_url", 'warning');
        return false;
    }

    // 获取当前队列
    public function get_queue()
    {
        return get_option($this->queue_option_name, array());
    }

    // 从队列中移除特定的RSS源URL
    public function remove_from_queue($feed_url)
    {
        $queue = $this->get_queue();
        $key = array_search($feed_url, $queue);
        if ($key !== false) {
            unset($queue[$key]);
            $this->update_queue(array_values($queue));
            $this->logger->log("从队列中移除: $feed_url", 'info');
            return true;
        }
        $this->logger->log("无法从队列中移除: $feed_url", 'warning');
        return false;
    }

    // 处理队列中的所有RSS源URL
    public function process_queue($importer)
    {
        $queue = $this->get_queue();
        $results = array();

        foreach ($queue as $feed_url) {
            try {
                $result = $importer->import_feed($feed_url);
                $results[$feed_url] = $result;
                $this->remove_from_queue($feed_url);
                $this->logger->log("处理队列项目: $feed_url, 结果: $result", 'info');
            } catch (Exception $e) {
                $this->logger->log("处理队列项目失败: $feed_url, 错误: " . $e->getMessage(), 'error');
                $results[$feed_url] = false;
            }
        }

        return $results;
    }

    // 清空整个队列
    public function clear_queue()
    {
        $result = delete_option($this->queue_option_name);
        if ($result) {
            $this->logger->log("队列已清空", 'info');
        } else {
            $this->logger->log("清空队列失败", 'error');
        }
        return $result;
    }

    // 获取当前队列大小
    public function get_queue_size()
    {
        return count($this->get_queue());
    }

    // 设置最大队列大小
    public function set_max_queue_size($size)
    {
        $this->max_queue_size = max(1, intval($size));
        $this->logger->log("设置最大队列大小: $this->max_queue_size", 'info');
    }

    // 获取最大队列大小
    public function get_max_queue_size()
    {
        return $this->max_queue_size;
    }

    // 检查队列是否已满
    public function is_queue_full()
    {
        return $this->get_queue_size() >= $this->max_queue_size;
    }

    // 更新队列
    private function update_queue($queue)
    {
        update_option($this->queue_option_name, $queue);
        $this->cache->delete('queue_status'); // 清除缓存的队列状态
    }

    // 获取队列状态
    public function get_queue_status()
    {
        $cached_status = $this->cache->get('queue_status');
        if ($cached_status !== false) {
            return $cached_status;
        }

        $queue_size = $this->get_queue_size();
        $status = array(
            'current_size' => $queue_size,
            'max_size' => $this->max_queue_size,
            'usage_percentage' => ($this->max_queue_size > 0) ? ($queue_size / $this->max_queue_size) * 100 : 0
        );

        $this->cache->set('queue_status', $status, 300); // 缓存5分钟
        return $status;
    }

    // 获取下一个要处理的队列项
    public function get_next_item()
    {
        $queue = $this->get_queue();
        return !empty($queue) ? $queue[0] : null;
    }

    // 重新排序队列
    public function reorder_queue($new_order)
    {
        if (count($new_order) !== $this->get_queue_size()) {
            $this->logger->log("重新排序队列失败: 新顺序与当前队列大小不匹配", 'error');
            return false;
        }

        $this->update_queue($new_order);
        $this->logger->log("队列已重新排序", 'info');
        return true;
    }

    // 获取队列处理进度
    public function get_processing_progress()
    {
        $total = $this->get_queue_size();
        $processed = $this->get_processed_count();
        return array(
            'total' => $total,
            'processed' => $processed,
            'percentage' => ($total > 0) ? ($processed / $total) * 100 : 0
        );
    }

    // 获取已处理的项目数量
    private function get_processed_count()
    {
        return intval(get_option('rss_news_importer_processed_count', 0));
    }

    // 增加已处理的项目数量
    public function increment_processed_count()
    {
        $count = $this->get_processed_count() + 1;
        update_option('rss_news_importer_processed_count', $count);
    }

    // 重置已处理的项目数量
    public function reset_processed_count()
    {
        update_option('rss_news_importer_processed_count', 0);
        $this->logger->log("已重置处理计数", 'info');
    }
}
