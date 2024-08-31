<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 包含必要的WordPress文件
require_once(ABSPATH . 'wp-admin/includes/admin.php');
require_once(ABSPATH . 'wp-includes/pluggable.php');

class RSS_News_Importer_Admin_Ajax
{
    // 插件名称
    private $plugin_name;

    // 插件版本
    private $version;

    // 选项名称
    private $option_name = 'rss_news_importer_options';

    // 日志记录器实例
    private $logger;

    // 导入器实例
    private $importer;

    /**
     * 构造函数
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     * @param RSS_News_Importer_Post_Importer $importer 导入器实例
     */
    public function __construct($plugin_name, $version, RSS_News_Importer_Post_Importer $importer)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
        $this->importer = $importer;
    }

    /**
     * 初始化钩子
     */
    public function init_hooks()
    {
        add_action('wp_ajax_rss_news_importer_import_now', array($this, 'import_now_ajax'));
        add_action('wp_ajax_rss_news_importer_add_feed', array($this, 'add_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_remove_feed', array($this, 'remove_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_preview_feed', array($this, 'preview_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_update_feed_order', array($this, 'update_feed_order_ajax'));
        add_action('wp_ajax_rss_news_importer_view_logs', array($this, 'view_logs_ajax'));
        add_action('wp_ajax_rss_news_importer_run_task', array($this, 'run_task_ajax'));
    }

    /**
     * 检查AJAX权限
     */
    private function check_ajax_permissions()
    {
        if (!check_ajax_referer('rss_news_importer_nonce', 'security', false)) {
            wp_send_json_error('Invalid security token sent.');
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action.');
        }
    }

    /**
     * AJAX: 立即导入
     */
    public function import_now_ajax()
    {
        $this->check_ajax_permissions();
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $results = array_map(function ($feed) {
            $feed_url = is_array($feed) ? $feed['url'] : $feed;
            $feed_name = is_array($feed) && isset($feed['name']) ? $feed['name'] : $feed_url;
            $result = $this->importer->import_feed($feed_url);
            return sprintf(__('Imported %d posts from %s', 'rss-news-importer'), $result, $feed_name);
        }, $feeds);

        wp_send_json_success(implode('<br>', $results));
    }

    /**
     * AJAX: 添加新的RSS源
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
     * AJAX: 移除RSS源
     */
    public function remove_feed_ajax()
    {
        $this->check_ajax_permissions();

        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            $this->logger->log('Attempt to remove feed with empty URL', 'error');
            wp_send_json_error('Invalid feed URL');
        }

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $this->logger->log('Removing feed - ' . $feed_url, 'info');
        $this->logger->log('Current feeds - ' . print_r($feeds, true), 'debug');

        $feeds = array_filter($feeds, function ($feed) use ($feed_url) {
            if (is_array($feed)) {
                return $feed['url'] !== $feed_url;
            }
            return $feed !== $feed_url;
        });

        $options['rss_feeds'] = array_values($feeds);

        $this->logger->log('Feeds after removal - ' . print_r($options['rss_feeds'], true), 'debug');

        $update_result = update_option($this->option_name, $options);

        if ($update_result) {
            $this->logger->log('Feed removed successfully', 'info');
            wp_send_json_success('Feed removed successfully');
        } else {
            $this->logger->log('Failed to remove feed. Current option value: ' . print_r(get_option($this->option_name), true), 'error');
            wp_send_json_error('Failed to remove feed');
        }
    }

    /**
     * AJAX: 预览RSS源
     */
    public function preview_feed_ajax()
    {
        $this->check_ajax_permissions();

        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error('Invalid feed URL');
        }

        $parser = new RSS_News_Importer_Parser();
        $preview_html = $parser->preview_feed($feed_url);

        if (is_wp_error($preview_html)) {
            wp_send_json_error($preview_html->get_error_message());
        } else {
            wp_send_json_success($preview_html);
        }
    }

    /**
     * AJAX: 更新RSS源顺序
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
     * AJAX: 查看日志
     */
    public function view_logs_ajax()
    {
        $this->check_ajax_permissions();
        $logs = $this->logger->get_logs();

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
     * AJAX: 运行任务
     */
    public function run_task_ajax()
    {
        $this->check_ajax_permissions();

        $task_name = isset($_POST['task_name']) ? sanitize_text_field($_POST['task_name']) : '';

        if (empty($task_name)) {
            wp_send_json_error(__('Invalid task name', 'rss-news-importer'));
        }

        // 这里需要实现任务运行的逻辑
        // 例如：
        // $result = $this->task_runner->run_task($task_name);

        // 临时的示例响应
        $result = "Task '$task_name' executed successfully";

        wp_send_json_success($result);
    }

    /**
     * 获取RSS源项目的HTML
     *
     * @param array $feed RSS源数据
     * @return string HTML字符串
     */
    private function get_feed_item_html($feed)
    {
        ob_start();
        ?>
        <div class="feed-item" data-feed-url="<?php echo esc_attr($feed['url']); ?>">
            <span class="dashicons dashicons-menu handle"></span>
            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[rss_feeds][]" value="<?php echo esc_url($feed['url']); ?>" readonly class="feed-url">
            <input type="text" name="<?php echo esc_attr($this->option_name); ?>[rss_feeds][]" value="<?php echo esc_attr($feed['name']); ?>" placeholder="<?php esc_attr_e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
            <button class="button remove-feed"><?php esc_html_e('Remove', 'rss-news-importer'); ?></button>
            <button class="button preview-feed"><?php esc_html_e('Preview', 'rss-news-importer'); ?></button>
        </div>
        <?php
        return ob_get_clean();
    }
}