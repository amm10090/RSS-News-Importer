<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
	exit;
}

/**
 * RSS新闻导入器菜单类
 */
class RSS_News_Importer_Menu
{
	// 插件名称
	private $plugin_name;

	// 插件版本
	private $version;

	// 管理类实例
	private $admin;

	// 选项名称
	private $option_name;

	/**
	 * 构造函数
	 *
	 * @param string $plugin_name 插件名称
	 * @param string $version 插件版本
	 * @param RSS_News_Importer_Admin $admin 管理类实例
	 */
	public function __construct($plugin_name, $version, $admin)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->admin = $admin;
		$this->option_name = 'rss_news_importer_options';
	}

	/**
	 * 添加插件管理菜单
	 */
	public function add_plugin_admin_menu()
	{
		// 添加主菜单
		add_menu_page(
			__('RSS News Importer', 'rss-news-importer'),
			__('RSS News Importer', 'rss-news-importer'),
			'manage_options',
			$this->plugin_name,
			array($this, 'display_plugin_setup_page'),
			'dashicons-rss',
			100
		);

		// 添加仪表板子菜单
		add_submenu_page(
			$this->plugin_name,
			__('RSS Feeds Dashboard', 'rss-news-importer'),
			__('Dashboard', 'rss-news-importer'),
			'manage_options',
			$this->plugin_name . '-dashboard',
			array($this, 'display_dashboard')
		);

		// 添加定时任务设置子菜单
		add_submenu_page(
			$this->plugin_name,
			__('Cron Settings', 'rss-news-importer'),
			__('Cron Settings', 'rss-news-importer'),
			'manage_options',
			$this->plugin_name . '-cron-settings',
			array($this, 'display_cron_settings_page')
		);

		// 添加导入日志子菜单
		add_submenu_page(
			$this->plugin_name,
			__('Import Logs', 'rss-news-importer'),
			__('Import Logs', 'rss-news-importer'),
			'manage_options',
			$this->plugin_name . '-logs',
			array($this, 'display_logs_page')
		);
	}

	/**
	 * 显示插件设置页面
	 */
	public function display_plugin_setup_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$this->admin->handle_settings_update();
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-display.php';
	}

	/**
	 * 显示仪表板页面
	 */
	public function display_dashboard()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$this->admin->prepare_dashboard();
		$this->admin->get_dashboard()->process_dashboard_actions();
		$this->admin->get_dashboard()->display_dashboard();
	}

	/**
	 * 显示定时任务设置页面
	 */
	public function display_cron_settings_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$cron_manager = $this->admin->get_cron_manager();
		$current_schedule = $cron_manager->get_current_schedule();
		$next_run = $cron_manager->get_next_scheduled_time();

		// 获取可用的定时任务计划
		$available_schedules = wp_get_schedules();

		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cron-settings-display.php';
	}

	/**
	 * 显示日志页面
	 */
	public function display_logs_page()
	{
		if (!current_user_can('manage_options')) {
			return;
		}
		$logs = $this->admin->get_logger()->get_logs();
		include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/logs-display.php';
	}
}
