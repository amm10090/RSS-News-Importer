<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
	exit;
}

class RSS_News_Importer_Post_Importer
{
	private $plugin_name;
	private $version;
	private $parser;
	private $logger;
	private $cache;
	private $option_name = 'rss_news_importer_options';
	private $image_scraper;
	private $content_filter;
	private $wpdb;

	public function __construct($plugin_name, $version, $cache)
	{
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->cache = $cache;
		$this->logger = new RSS_News_Importer_Logger();
		$this->parser = new RSS_News_Importer_Parser($this->logger, $this->cache);
		$this->image_scraper = new RSS_News_Importer_Image_Scraper($this->logger);
		$this->content_filter = new RSS_News_Importer_Content_Filter($this->logger);
	}

	public function import_feed($url, $force_refresh = false)
	{
		$this->logger->log("开始导入RSS源: $url", 'info');

		$options = get_option($this->option_name);
		$import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;

		try {
			$feed_data = $this->parser->fetch_feed($url, $force_refresh);
			if (is_wp_error($feed_data)) {
				throw new Exception($feed_data->get_error_message());
			}

			$last_import_time = $this->get_last_import_time($url);
			$new_items = $this->filter_new_items($feed_data, $last_import_time);

			if (empty($new_items)) {
				$this->logger->log("没有新的内容需要导入", 'info');
				return 0;
			}

			$this->wpdb->query('START TRANSACTION');

			$imported_count = $this->process_feed_items($new_items, $url, $import_limit);

			if ($imported_count === false) {
				throw new Exception("处理feed数据时发生错误");
			}

			$this->wpdb->query('COMMIT');

			$this->update_last_import_time($url);

			$this->logger->log("成功导入 $imported_count 篇文章，来自 $url", 'info');
			return $imported_count;
		} catch (Exception $e) {
			$this->wpdb->query('ROLLBACK');
			$this->logger->log("导入失败: " . $e->getMessage(), 'error');
			return new WP_Error('import_error', $e->getMessage());
		}
	}

	private function filter_new_items($feed_data, $last_import_time)
	{
		return array_filter($feed_data, function ($item) use ($last_import_time) {
			$item_time = isset($item['pubDate']) ? strtotime($item['pubDate']) : time();
			return $item_time > $last_import_time;
		});
	}

	private function process_feed_items($items, $url, $import_limit)
	{
		$imported_count = 0;
		$skipped_count = 0;

		foreach (array_slice($items, 0, $import_limit) as $item) {
			$result = $this->import_item($item, $url);
			if ($result === true) {
				$imported_count++;
			} elseif ($result === 'skipped') {
				$skipped_count++;
			} else {
				$this->logger->log("导入项目失败: " . $item['title'], 'error');
			}
		}

		$this->logger->log("从 $url 导入了 $imported_count 篇文章 (跳过了 $skipped_count 篇重复文章)", 'info');
		return $imported_count;
	}

	private function import_item($item, $feed_url)
	{
		try {
			if (!isset($item['title']) || !isset($item['link'])) {
				throw new Exception("RSS项目缺少必要的字段");
			}

			$guid = isset($item['guid']) ? $item['guid'] : $item['link'];
			if ($this->post_exists($guid)) {
				$this->logger->log("跳过重复项目: " . $item['title'], 'info');
				return 'skipped';
			}

			$post_content = isset($item['content']) ? $item['content'] : (isset($item['description']) ? $item['description'] : '');
			$post_content = $this->content_filter->filter_content($post_content);
			$post_content = $this->content_filter->sanitize_html($post_content);

			$options = get_option($this->option_name);
			$post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';

			$post_data = array(
				'post_title'    => wp_strip_all_tags($item['title']),
				'post_content'  => $post_content,
				'post_excerpt'  => isset($item['description']) ? wp_trim_words($item['description'], 55, '...') : '',
				'post_date'     => isset($item['pubDate']) ? date('Y-m-d H:i:s', strtotime($item['pubDate'])) : current_time('mysql'),
				'post_status'   => $post_status,
				'post_author'   => $this->get_default_author(),
				'post_type'     => 'post',
				'post_category' => $this->get_default_category(),
				'meta_input'    => array(
					'rss_news_importer_guid' => $guid,
					'rss_news_importer_link' => $item['link'],
					'rss_news_importer_author' => isset($item['author']) ? $item['author'] : '',
					'rss_news_importer_feed_url' => $feed_url,
					'rss_news_importer_import_date' => current_time('mysql'),
				),
			);

			$post_id = wp_insert_post($post_data);

			if (is_wp_error($post_id)) {
				throw new Exception("导入项目失败: " . $item['title'] . ". 错误: " . $post_id->get_error_message());
			}

			$this->image_scraper->set_post_thumbnail($post_id, $item);
			$this->set_post_tags($post_id, $feed_url);

			$this->logger->log("成功导入文章: " . $item['title'], 'info');
			return true;
		} catch (Exception $e) {
			$this->logger->log("导入项目时发生异常: " . $e->getMessage(), 'error');
			return false;
		}
	}

	private function set_post_tags($post_id, $feed_url)
	{
		$options = get_option($this->option_name);
		$feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
		$feed_name = '';
		foreach ($feeds as $feed) {
			if ($feed['url'] === $feed_url) {
				$feed_name = $feed['name'];
				break;
			}
		}
		if (!empty($feed_name)) {
			wp_set_post_tags($post_id, $feed_name, true);
		}
	}

	private function post_exists($guid)
	{
		$cache_key = 'post_exists_' . md5($guid);
		$exists = $this->cache->get($cache_key);

		if ($exists === false) {
			$exists = $this->wpdb->get_var($this->wpdb->prepare(
				"SELECT post_id FROM {$this->wpdb->postmeta} WHERE meta_key='rss_news_importer_guid' AND meta_value=%s",
				$guid
			));
			$this->cache->set($cache_key, $exists ? true : false);
		}

		return (bool) $exists;
	}

	private function get_default_author()
	{
		$options = get_option($this->option_name);
		return isset($options['import_author']) ? intval($options['import_author']) : 1;
	}

	private function get_default_category()
	{
		$options = get_option($this->option_name);
		$category_id = isset($options['import_category']) ? intval($options['import_category']) : 1;
		return array($category_id);
	}

	public function import_all_feeds($feeds)
	{
		$total_imported = 0;
		foreach ($feeds as $feed) {
			$feed_url = is_array($feed) ? $feed['url'] : $feed;
			$imported_count = $this->import_feed($feed_url);
			if (!is_wp_error($imported_count)) {
				$total_imported += $imported_count;
			}
		}
		return $total_imported;
	}

	private function get_last_import_time($url)
	{
		return $this->cache->get_feed_last_update($url) ?: 0;
	}

	private function update_last_import_time($url)
	{
		$this->cache->set_feed_last_update($url, time());
	}
	//获取最后的时间
	private function get_last_import_time_global()
	{
		$last_import = $this->wpdb->get_var(
			"SELECT meta_value FROM {$this->wpdb->postmeta} 
        WHERE meta_key = 'rss_news_importer_import_date' 
        ORDER BY meta_value DESC 
        LIMIT 1"
		);
		return $last_import ? $last_import : __('Never', 'rss-news-importer');
	}

	public function get_import_stats()
	{
		$stats = array(
			'imports_today' => $this->get_imports_count_for_period('today'),
			'imports_week' => $this->get_imports_count_for_period('week'),
			'total_imports' => $this->get_total_imports_count(),
			'last_import' => $this->get_last_import_time_global(), // 修改这一行
			'average_import_time' => $this->get_average_import_time(),
		);

		$this->cache->set('import_stats', $stats, 3600); // 缓存1小时

		return $stats;
	}

	public function get_imports_count_for_period($period)
	{
		$date = '';
		switch ($period) {
			case 'today':
				$date = date('Y-m-d');
				break;
			case 'week':
				$date = date('Y-m-d', strtotime('-1 week'));
				break;
			default:
				return 0;
		}
		$count = $this->wpdb->get_var($this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date' 
            AND meta_value >= %s",
			$date
		));
		return intval($count);
	}

	public function get_total_imports_count()
	{
		$count = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date'"
		);
		return intval($count);
	}

	public function get_average_import_time()
	{
		$avg_time = $this->wpdb->get_var(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, p.post_date, pm.meta_value)) 
            FROM {$this->wpdb->posts} p 
            JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE pm.meta_key = 'rss_news_importer_import_date'"
		);
		return round(floatval($avg_time), 2);
	}

	public function get_recent_imports($limit = 10)
	{
		$recent_imports = $this->wpdb->get_results($this->wpdb->prepare(
			"SELECT p.ID, p.post_title, pm.meta_value as import_date, pm2.meta_value as feed_url
            FROM {$this->wpdb->posts} p 
            JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id 
            JOIN {$this->wpdb->postmeta} pm2 ON p.ID = pm2.post_id 
            WHERE pm.meta_key = 'rss_news_importer_import_date'
            AND pm2.meta_key = 'rss_news_importer_feed_url'
            ORDER BY pm.meta_value DESC 
            LIMIT %d",
			$limit
		));
		return array_map(function ($import) {
			return array(
				'id' => $import->ID,
				'title' => $import->post_title,
				'import_date' => $import->import_date,
				'feed_url' => $import->feed_url
			);
		}, $recent_imports);
	}

	public function get_rss_feeds()
	{
		$options = get_option($this->option_name);
		return isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
	}
}
