<?php

// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin
{
    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';
    private $cron_manager;

    /**
     * 构造函数：初始化插件并设置钩子
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->option_name = 'rss_news_importer_options';
        $this->cron_manager = new RSS_News_Importer_Cron_Manager($plugin_name, $version);

        $this->init_hooks();
        $this->register_ajax_actions();
    }

    /**
     * 初始化所有钩子
     */
    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action($this->cron_manager->get_cron_hook(), array($this->cron_manager, 'run_tasks'));
        $this->register_ajax_actions();

        // 检查选项是否存在，如果不存在则创建
        if (false === get_option($this->option_name)) {
            add_option($this->option_name, array());
        }
    }

    /**
     * 注册AJAX动作
     */
    private function register_ajax_actions()
    {
        $ajax_actions = array(
            'import_now',
            'add_feed',
            'remove_feed',
            'view_logs',
            'preview_feed',
            'update_feed_order',
            'get_logs',
            'clear_logs',
            'run_cron_now'
        );

        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_rss_news_importer_' . $action, array($this, $action . '_ajax'));
            add_action('wp_ajax_nopriv_rss_news_importer_' . $action, array($this, $action . '_ajax'));
        }
    }

    /**
     * 加载管理页面样式
     */
    public function enqueue_styles($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
        }
    }

    /**
     * 加载管理页面脚本
     */
    public function enqueue_scripts($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery', 'jquery-ui-sortable'), $this->version, false);
            wp_localize_script($this->plugin_name, 'rss_news_importer_ajax', $this->get_ajax_data());

            // 加载 React 和 ReactDOM
            $this->enqueue_react_scripts();
        }
    }

    /**
     * 加载 React 和 ReactDOM 脚本
     */
    private function enqueue_react_scripts()
    {
        wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', array(), '17.0.2', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', array('react'), '17.0.2', true);
        wp_enqueue_script('log-viewer-component', plugin_dir_url(__FILE__) . 'js/log-viewer-component.js', array('react', 'react-dom'), $this->version, true);
    }

    /**
     * 获取AJAX数据
     */
    private function get_ajax_data()
    {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rss_news_importer_nonce'),
            'i18n' => array(
                'add_feed_prompt' => __('Enter the URL of the RSS feed you want to add:', 'rss-news-importer'),
                'remove_text' => __('Remove', 'rss-news-importer'),
                'importing_text' => __('Importing...', 'rss-news-importer'),
                'error_text' => __('An error occurred. Please try again.', 'rss-news-importer'),
                'running_text' => __('Running...', 'rss-news-importer'),
                'run_now_text' => __('Run Now', 'rss-news-importer')
            )
        );
    }

    /**
     * 添加插件管理菜单
     */
    public function add_plugin_admin_menu()
    {
        add_menu_page(
            __('RSS News Importer', 'rss-news-importer'),
            __('RSS News Importer', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page'),
            'dashicons-rss',
            100
        );

        add_submenu_page(
            $this->plugin_name,
            __('RSS Feeds Dashboard', 'rss-news-importer'),
            __('Dashboard', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-dashboard',
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Cron Settings', 'rss-news-importer'),
            __('Cron Settings', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-cron-settings',
            array($this, 'display_cron_settings_page')
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

        $this->handle_settings_update();
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-display.php';
    }

    /**
     * 显示仪表板页面
     */
    public function display_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $dashboard = new RSS_News_Importer_Dashboard($this->plugin_name, $this->version);
        $dashboard->display_dashboard();
    }

    /**
     * 显示定时任务设置页面
     */
    public function display_cron_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cron_manager = $this->cron_manager;
        $current_schedule = $cron_manager->get_current_schedule();
        $next_run = $cron_manager->get_next_scheduled_time();
        $available_schedules = $cron_manager->get_available_schedules();

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cron-settings-display.php';
    }

    /**
     * 处理设置更新
     */
    private function handle_settings_update()
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('Settings Saved', 'rss-news-importer'), 'updated');
        }
        settings_errors('rss_news_importer_messages');
    }

    /**
     * 注册插件设置
     */
    public function register_settings()
    {
        register_setting($this->plugin_name, $this->option_name, array($this, 'validate_options'));
        $this->add_settings_sections();
        $this->add_settings_fields();

        // 注册定时任务设置
        register_setting('rss_news_importer_cron_settings', 'rss_news_importer_cron_schedule');
    }

    /**
     * 添加设置部分
     */
    private function add_settings_sections()
    {
        add_settings_section(
            'rss_news_importer_general',
            __('General Settings', 'rss-news-importer'),
            array($this, 'general_settings_section_cb'),
            $this->plugin_name
        );
    }

    /**
     * 添加设置字段
     */
    private function add_settings_fields()
    {
        $fields = array(
            'rss_feeds' => __('RSS Feeds', 'rss-news-importer'),
            'update_frequency' => __('Update Frequency', 'rss-news-importer'),
            'import_options' => __('Import Options', 'rss-news-importer'),
            'thumbnail_settings' => __('Thumbnail Settings', 'rss-news-importer'),
            'import_limit' => __('Import Limit', 'rss-news-importer'),
            'content_exclusions' => __('Content Exclusions', 'rss-news-importer')
        );

        foreach ($fields as $field => $title) {
            add_settings_field(
                $field,
                $title,
                array($this, $field . '_cb'),
                $this->plugin_name,
                'rss_news_importer_general'
            );
        }
    }

    /**
     * 通用设置部分回调
     */
    public function general_settings_section_cb()
    {
        echo '<p>' . __('Configure your RSS News Importer settings here.', 'rss-news-importer') . '</p>';
    }

    /**
     * RSS源设置字段回调
     */
    public function rss_feeds_cb()
    {
        $options = get_option($this->option_name);
        $rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        if (!is_array($rss_feeds)) {
            $rss_feeds = array();
        }

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-feeds-list.php';
    }

    /**
     * 更新频率设置字段回调
     */
    public function update_frequency_cb()
    {
        $options = get_option($this->option_name);
        $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly';

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/update-frequency.php';
    }

    /**
     * 导入选项设置字段回调
     */
    public function import_options_cb()
    {
        $options = get_option($this->option_name);
        $category = isset($options['import_category']) ? $options['import_category'] : '';
        $author = isset($options['import_author']) ? $options['import_author'] : get_current_user_id();

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/import-options.php';
    }

    /**
     * 缩略图设置字段回调
     */
    public function thumbnail_settings_cb()
    {
        $options = get_option($this->option_name);
        $thumb_size = isset($options['thumb_size']) ? $options['thumb_size'] : 'thumbnail';
        $force_thumb = isset($options['force_thumb']) ? $options['force_thumb'] : 0;

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/thumbnail-settings.php';
    }

    /**
     * 导入限制设置字段回调
     */
    public function import_limit_cb()
    {
        $options = get_option($this->option_name);
        $import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/import-limit.php';
    }

    /**
     * 内容排除设置字段回调
     */
    public function content_exclusions_cb()
    {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/content-exclusions.php';
    }

    /**
     * 验证选项
     */
    public function validate_options($input)
    {
        $valid = array();

        // 验证 RSS 源
        if (isset($input['rss_feeds']) && is_array($input['rss_feeds'])) {
            $valid['rss_feeds'] = $this->sanitize_rss_feeds($input['rss_feeds']);
        } else {
            $valid['rss_feeds'] = array();
        }

        // 验证更新频率
        $valid['update_frequency'] = isset($input['update_frequency'])
            ? sanitize_text_field($input['update_frequency'])
            : 'daily';

        // 验证导入分类
        $valid['import_category'] = isset($input['import_category'])
            ? absint($input['import_category'])
            : 0;

        // 验证导入作者
        $valid['import_author'] = isset($input['import_author'])
            ? absint($input['import_author'])
            : get_current_user_id();

        // 验证缩略图大小
        $valid['thumb_size'] = isset($input['thumb_size'])
            ? sanitize_text_field($input['thumb_size'])
            : 'thumbnail';

        // 验证强制生成缩略图选项
        $valid['force_thumb'] = isset($input['force_thumb']) ? 1 : 0;

        // 验证导入限制
        $valid['import_limit'] = isset($input['import_limit'])
            ? intval($input['import_limit'])
            : 10;

        // 验证内容排除
        $valid['content_exclusions'] = isset($input['content_exclusions'])
            ? sanitize_textarea_field($input['content_exclusions'])
            : '';

        return $valid;
    }

    /**
     * 清理RSS源数据
     */
    private function sanitize_rss_feeds($feeds)
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

    /**
     * 检查AJAX权限
     */
    private function check_ajax_permissions()
    {
        if (!check_ajax_referer('rss_news_importer_nonce', 'security', false)) {
            wp_send_json_error('Invalid security token sent.');
            exit;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action.');
            exit;
        }
    }

    /**
     * AJAX处理方法: 立即导入
     */
    public function import_now_ajax()
    {
        $this->check_ajax_permissions();
        $importer = new RSS_News_Importer_Post_Importer($this->plugin_name, $this->version);
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $results = array_map(function ($feed) use ($importer) {
            $feed_url = is_array($feed) ? $feed['url'] : $feed;
            $feed_name = is_array($feed) && isset($feed['name']) ? $feed['name'] : $feed_url;
            $result = $importer->import_feed($feed_url);
            return sprintf(__('Imported %d posts from %s', 'rss-news-importer'), $result, $feed_name);
        }, $feeds);

        wp_send_json_success(implode('<br>', $results));
    }

    /**
     * AJAX处理方法: 添加RSS源
     */
    public function add_feed_ajax()
    {
        $this->check_ajax_permissions();
        $new_feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        $new_feed_name = isset($_POST['feed_name']) ? sanitize_text_field($_POST['feed_name']) : '';

        if (empty($new_feed_url)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }

        $options = get_option($this->option_name, array());
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        if (!is_array($feeds)) {
            $feeds = array();
        }

        $new_feed = array('url' => $new_feed_url, 'name' => $new_feed_name);
        $feeds[] = $new_feed;
        $options['rss_feeds'] = $feeds;

        $save_result = update_option($this->option_name, $options);

        if ($save_result) {
            $html = $this->get_feed_item_html($new_feed);
            wp_send_json_success(array(
                'message' => __('Feed added successfully', 'rss-news-importer'),
                'html' => $html
            ));
        } else {
            wp_send_json_error(__('Failed to save feed', 'rss-news-importer'));
        }
    }

    /**
     * AJAX处理方法: 移除RSS源
     */
    public function remove_feed_ajax()
    {
        $this->check_ajax_permissions();

        error_log('Remove feed AJAX called'); // 调试日志

        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error('Invalid feed URL');
            exit;
        }

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $feeds = array_filter($feeds, function ($feed) use ($feed_url) {
            return (is_array($feed) && $feed['url'] !== $feed_url) || $feed !== $feed_url;
        });

        $options['rss_feeds'] = array_values($feeds);
        $update_result = update_option($this->option_name, $options);

        if ($update_result) {
            wp_send_json_success('Feed removed successfully');
        } else {
            wp_send_json_error('Failed to remove feed');
        }
        exit;
    }
    /**
     * AJAX处理方法: 查看日志
     */
    public function view_logs_ajax()
    {
        $this->check_ajax_permissions();
        $logger = new RSS_News_Importer_Logger();
        $logs = $logger->get_logs();

        $log_html = '<table class="wp-list-table widefat fixed striped">';
        $log_html .= '<thead><tr><th>' . __('Date', 'rss-news-importer') . '</th><th>' . __('Message', 'rss-news-importer') . '</th><th>' . __('Type', 'rss-news-importer') . '</th></tr></thead><tbody>';

        foreach ($logs as $log) {
            $log_html .= '<tr>';
            $log_html .= '<td>' . esc_html($log['date']) . '</td>';
            $log_html .= '<td>' . esc_html($log['message']) . '</td>';
            $log_html .= '<td>' . esc_html($log['type']) . '</td>';
            $log_html .= '</tr>';
        }

        $log_html .= '</tbody></table>';

        wp_send_json_success($log_html);
    }

    /**
     * AJAX处理方法: 预览RSS源
     */
    public function preview_feed_ajax()
    {
        $this->check_ajax_permissions();

        error_log('Preview feed AJAX called'); // 调试日志

        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error('Invalid feed URL');
            exit;
        }

        $parser = new RSS_News_Importer_Parser();
        $feed_data = $parser->fetch_feed($feed_url);

        if (!$feed_data) {
            wp_send_json_error('Error fetching feed');
            exit;
        }

        $preview_html = '<ul>';
        foreach (array_slice($feed_data, 0, 5) as $item) {
            $preview_html .= '<li>';
            $preview_html .= '<h3>' . esc_html($item['title']) . '</h3>';
            $preview_html .= '<p>' . wp_trim_words($item['description'], 55, '...') . '</p>';
            $preview_html .= '</li>';
        }
        $preview_html .= '</ul>';

        wp_send_json_success($preview_html);
        exit;
    }
    /**
     * AJAX处理方法: 更新RSS源顺序
     */
    public function update_feed_order_ajax()
    {
        $this->check_ajax_permissions();
        $new_order = isset($_POST['order']) ? $_POST['order'] : array();

        if (empty($new_order)) {
            wp_send_json_error(__('Invalid feed order', 'rss-news-importer'));
        }

        $options = get_option($this->option_name);
        $current_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $reordered_feeds = array();
        foreach ($new_order as $feed_url) {
            $feed = array_filter($current_feeds, function ($item) use ($feed_url) {
                return (is_array($item) && $item['url'] === $feed_url) || $item === $feed_url;
            });
            if (!empty($feed)) {
                $reordered_feeds[] = reset($feed);
            }
        }

        $options['rss_feeds'] = $reordered_feeds;
        update_option($this->option_name, $options);

        wp_send_json_success(__('Feed order updated successfully', 'rss-news-importer'));
    }

    /**
     * AJAX处理方法: 获取日志
     */
    public function get_logs_ajax()
    {
        $this->check_ajax_permissions();
        $logger = new RSS_News_Importer_Logger();
        $logs = $logger->get_logs();

        if ($logs === false) {
            wp_send_json_error('无法读取日志文件');
        } else {
            wp_send_json_success($logs);
        }
    }

    /**
     * AJAX处理方法: 清除日志
     */
    public function clear_logs_ajax()
    {
        $this->check_ajax_permissions();
        $logger = new RSS_News_Importer_Logger();
        $result = $logger->clear_logs();

        if ($result) {
            wp_send_json_success('All logs have been deleted.');
        } else {
            wp_send_json_error('Failed to delete logs.');
        }
    }

    /**
     * AJAX处理方法: 立即运行定时任务
     */
    public function run_cron_now_ajax()
    {
        check_ajax_referer('rss_news_importer_run_cron_now', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'rss-news-importer'));
        }

        $this->cron_manager->run_import_now();

        wp_send_json_success(__('RSS import executed successfully.', 'rss-news-importer'));
    }

    /**
     * 处理定时任务设置保存
     */
    public function handle_cron_settings_save()
    {
        if (isset($_POST['rss_news_importer_cron_schedule'])) {
            $new_schedule = sanitize_text_field($_POST['rss_news_importer_cron_schedule']);
            $this->cron_manager->update_schedule($new_schedule);
        }
    }

    /**
     * 获取单个RSS源的HTML
     */
    private function get_feed_item_html($feed)
    {
        ob_start();
?>
        <div class="feed-item" data-feed-url="<?php echo esc_attr($feed['url']); ?>">
            <span class="dashicons dashicons-menu handle"></span>
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_url($feed['url']); ?>" readonly class="feed-url">
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_attr($feed['name']); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
            <button class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
            <button class="button preview-feed"><?php _e('Preview', 'rss-news-importer'); ?></button>
        </div>
<?php
        return ob_get_clean();
    }
}
