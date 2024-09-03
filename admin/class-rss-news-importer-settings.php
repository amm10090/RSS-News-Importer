<?php
if (!defined('ABSPATH')) {
	exit;
}
class RSS_News_Importer_Settings
{
	private $plugin_name;
	private $version;
	private $option_name;
	private $admin;
	public function __construct($plugin_name, $version, $option_name, $admin)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->option_name = $option_name;
		$this->admin = $admin;
	}
	// 注册插件设置
	public function register_settings()
	{
		register_setting($this->plugin_name, $this->option_name, array($this, 'validate_options'));
		// 确保cron设置也有验证函数
		register_setting('rss_news_importer_cron_settings', 'rss_news_importer_cron_schedule', array($this, 'validate_cron_options'));
		$this->add_settings_sections();
		$this->add_settings_fields();
	}
	// 添加设置部分
	private function add_settings_sections()
	{
		add_settings_section(
			'rss_news_importer_general',
			__('General Settings', 'rss-news-importer'),
			array($this, 'general_settings_section_cb'),
			$this->plugin_name
		);
		add_settings_section(
			'rss_news_importer_advanced',
			__('Advanced Settings', 'rss-news-importer'),
			array($this, 'advanced_settings_section_cb'),
			$this->plugin_name
		);
	}
	// 添加设置字段
	private function add_settings_fields()
	{
		$general_fields = array(
			'rss_feeds' => __('RSS Feeds', 'rss-news-importer'),
			'import_options' => __('Import Options', 'rss-news-importer'),
			'thumbnail_settings' => __('Thumbnail Settings', 'rss-news-importer'),
			'import_limit' => __('Import Limit', 'rss-news-importer'),
			'post_status' => __('Post Status', 'rss-news-importer'),
		);
		$advanced_fields = array(
			'content_exclusions' => __('Content Exclusions', 'rss-news-importer'),
			'cache_settings' => __('Cache Settings', 'rss-news-importer'),
			'error_handling' => __('Error Handling', 'rss-news-importer'),
		);
		foreach ($general_fields as $field => $title) {
			add_settings_field(
				$field,
				$title,
				array($this, $field . '_cb'),
				$this->plugin_name,
				'rss_news_importer_general'
			);
		}
		foreach ($advanced_fields as $field => $title) {
			add_settings_field(
				$field,
				$title,
				array($this, $field . '_cb'),
				$this->plugin_name,
				'rss_news_importer_advanced'
			);
		}
	}
	// 通用设置部分回调
	public function general_settings_section_cb()
	{
		echo '<p>' . __('Configure your RSS News Importer general settings here.', 'rss-news-importer') . '</p>';
	}
	// 高级设置部分回调
	public function advanced_settings_section_cb()
	{
		echo '<p>' . __('Configure advanced settings for RSS News Importer. Be cautious when changing these settings.', 'rss-news-importer') . '</p>';
	}
	// RSS源设置字段回调
	public function rss_feeds_cb()
	{
		$options = get_option($this->option_name);
		$rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
		if (!is_array($rss_feeds)) {
			$rss_feeds = array();
		}
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-feeds-list.php';
	}
	// 导入选项设置字段回调
	public function import_options_cb()
	{
		$options = get_option($this->option_name);
		$category = isset($options['import_category']) ? $options['import_category'] : '';
		$author = isset($options['import_author']) ? $options['import_author'] : get_current_user_id();
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/import-options.php';
	}
	// 帖子状态设置字段回调
	public function post_status_cb()
	{
		$options = get_option($this->option_name);
		$post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/post-status.php';
	}
	// 缩略图设置字段回调
	public function thumbnail_settings_cb()
	{
		$options = get_option($this->option_name);
		$thumb_size = isset($options['thumb_size']) ? $options['thumb_size'] : 'thumbnail';
		$force_thumb = isset($options['force_thumb']) ? $options['force_thumb'] : 0;
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/thumbnail-settings.php';
	}
	// 导入限制设置字段回调
	public function import_limit_cb()
	{
		$options = get_option($this->option_name);
		$import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/import-limit.php';
	}
	// 内容排除设置字段回调
	public function content_exclusions_cb()
	{
		$options = get_option($this->option_name);
		$exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/content-exclusions.php';
	}
	// 缓存设置字段回调
	public function cache_settings_cb()
	{
		$options = get_option($this->option_name);
		$cache_expiration = isset($options['cache_expiration']) ? intval($options['cache_expiration']) : 3600;
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cache-settings.php';
	}
	// 错误处理设置字段回调
	public function error_handling_cb()
	{
		$options = get_option($this->option_name);
		$error_retry = isset($options['error_retry']) ? intval($options['error_retry']) : 3;
		$error_notify = isset($options['error_notify']) ? $options['error_notify'] : '';
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/error-handling.php';
	}
	// 验证选项
	public static function validate_options($input)
	{
		$valid = array();
		// 验证并清理每个选项
		$valid['rss_feeds'] = isset($input['rss_feeds']) ? self::sanitize_rss_feeds($input['rss_feeds']) : array();
		$valid['import_category'] = isset($input['import_category']) ? absint($input['import_category']) : 0;
		$valid['import_author'] = isset($input['import_author']) ? absint($input['import_author']) : get_current_user_id();
		$valid['thumb_size'] = isset($input['thumb_size']) ? sanitize_text_field($input['thumb_size']) : 'thumbnail';
		$valid['force_thumb'] = isset($input['force_thumb']) ? 1 : 0;
		$valid['import_limit'] = isset($input['import_limit']) ? intval($input['import_limit']) : 10;
		$valid['content_exclusions'] = isset($input['content_exclusions']) ? sanitize_textarea_field($input['content_exclusions']) : '';
		$valid['cache_expiration'] = isset($input['cache_expiration']) ? intval($input['cache_expiration']) : 3600;
		$valid['error_retry'] = isset($input['error_retry']) ? intval($input['error_retry']) : 3;
		$valid['error_notify'] = isset($input['error_notify']) ? sanitize_email($input['error_notify']) : '';
		$valid['post_status'] = isset($input['post_status']) ? sanitize_text_field($input['post_status']) : 'draft';
		// 验证内容净化
		$valid['unwanted_elements'] = isset($input['unwanted_elements']) ? array_map('sanitize_text_field', $input['unwanted_elements']) : array();
		$valid['unwanted_attributes'] = isset($input['unwanted_attributes']) ? array_map('sanitize_text_field', $input['unwanted_attributes']) : array();
		$valid['iframe_policy'] = isset($input['iframe_policy']) ? sanitize_text_field($input['iframe_policy']) : 'remove';
		if (!in_array($valid['iframe_policy'], array('remove', 'placeholder', 'allow'))) {
			$valid['iframe_policy'] = 'remove';
		}
		$valid['max_content_length'] = isset($input['max_content_length']) ? intval($input['max_content_length']) : 0;
		if ($valid['max_content_length'] < 0) {
			$valid['max_content_length'] = 0;
		}
		$valid['base_url'] = isset($input['base_url']) ? esc_url_raw($input['base_url']) : '';
		$valid['remove_empty_paragraphs'] = isset($input['remove_empty_paragraphs']) ? (bool)$input['remove_empty_paragraphs'] : false;
		$valid['convert_relative_urls'] = isset($input['convert_relative_urls']) ? (bool)$input['convert_relative_urls'] : false;
		$valid['sanitize_html'] = isset($input['sanitize_html']) ? (bool)$input['sanitize_html'] : false;
		$valid['remove_malicious_content'] = isset($input['remove_malicious_content']) ? (bool)$input['remove_malicious_content'] : false;
		// Cron 设置验证
		if (isset($input['rss_update_schedule'])) {
			$valid['rss_update_schedule'] = sanitize_text_field($input['rss_update_schedule']);
			if (!array_key_exists($valid['rss_update_schedule'], wp_get_schedules()) && $valid['rss_update_schedule'] !== 'custom') {
				add_settings_error('rss_news_importer_messages', 'rss_update_schedule', __('选择的计划无效。', 'rss-news-importer'));
			}
		}

		if (isset($input['custom_cron_interval'])) {
			$valid['custom_cron_interval'] = intval($input['custom_cron_interval']);
			if ($valid['rss_update_schedule'] === 'custom' && $valid['custom_cron_interval'] < 1) {
				add_settings_error('rss_news_importer_messages', 'custom_cron_interval', __('自定义间隔必须至少为1分钟。', 'rss-news-importer'));
			}
		}

		if (isset($input['update_method'])) {
			$valid['update_method'] = sanitize_text_field($input['update_method']);
			if (!in_array($valid['update_method'], array('bulk', 'individual'))) {
				add_settings_error('rss_news_importer_messages', 'update_method', __('选择的更新方法无效。', 'rss-news-importer'));
			}
		}
		// 注意：这里不能使用 $this->option_name，因为这是静态方法
		// 如果需要使用 option_name，可以将其作为参数传入或使用常量
		return $valid;
	}
	// 验证cron设置的函数
	public function validate_cron_options($input)
	{
		$valid = array();
		$valid['rss_news_importer_cron_schedule'] = isset($input['rss_news_importer_cron_schedule']) ? sanitize_text_field($input['rss_news_importer_cron_schedule']) : '';
		return $valid;
	}
	// 清理RSS源
	private static  function sanitize_rss_feeds($feeds)
	{
		$sanitized_feeds = array();
		foreach ($feeds as $feed) {
			if (is_array($feed)) {
				$sanitized_feed = array(
					'url' => esc_url_raw($feed['url']),
					'name' => sanitize_text_field(isset($feed['name']) ? $feed['name'] : '')
				);
				if (!empty($sanitized_feed['url'])) {
					$sanitized_feeds[] = $sanitized_feed;
				}
			} elseif (is_string($feed)) {
				$sanitized_url = esc_url_raw($feed);
				if (!empty($sanitized_url)) {
					$sanitized_feeds[] = $sanitized_url;
				}
			}
		}
		return $sanitized_feeds;
	}
}
