<?php
// 如果直接访问此文件,则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin_Ajax
{
    private $admin;
    private $logger;
    private $importer;
    private $cron_manager;

    public function generate_nonce_ajax()
    {
        $nonce = wp_create_nonce('rss_news_importer_save_settings');
        wp_send_json_success(['nonce' => $nonce]);
    }

    public function __construct($admin)
    {
        add_action('wp_ajax_rss_news_importer_generate_nonce', array($this, 'generate_nonce_ajax'));
        $this->admin = $admin;
        $this->logger = new RSS_News_Importer_Logger();
        $this->cron_manager = new RSS_News_Importer_Cron_Manager($admin->get_plugin_name(), $admin->get_version());
        $this->importer = new RSS_News_Importer_Post_Importer($admin->get_plugin_name(), $admin->get_version(), $admin->get_cache()); // 使用 $admin->get_cache()
        $this->init_ajax_hooks();
    }

    private function init_ajax_hooks()
    {
        add_action('wp_ajax_rss_news_importer_import_now', array($this, 'import_now_ajax'));
        add_action('wp_ajax_rss_news_importer_add_feed', array($this, 'add_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_remove_feed', array($this, 'remove_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_preview_feed', array($this, 'preview_feed_ajax'));
        add_action('wp_ajax_rss_news_importer_update_feed_order', array($this, 'update_feed_order_ajax'));
        add_action('wp_ajax_rss_news_importer_get_logs', array($this, 'get_logs_ajax'));
        add_action('wp_ajax_rss_news_importer_clear_logs', array($this, 'clear_logs_ajax'));
        add_action('wp_ajax_rss_news_importer_run_cron_now', array($this, 'run_cron_now_ajax'));
        add_action('wp_ajax_rss_news_importer_save_settings', array($this, 'save_settings_ajax'));
        add_action('wp_ajax_rss_news_importer_clear_cache', array($this, 'clear_cache_ajax'));
        add_action('wp_ajax_rss_news_importer_refresh_logs', array($this, 'refresh_logs_ajax'));
    }

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

    public function add_feed_ajax()
    {
        $this->check_ajax_permissions();
        $new_feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        $new_feed_name = isset($_POST['feed_name']) ? sanitize_text_field($_POST['feed_name']) : '';
        if (empty($new_feed_url)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }
        $options = get_option($this->admin->get_option_name(), array());
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        if (!is_array($feeds)) {
            $feeds = array();
        }
        $new_feed = array('url' => $new_feed_url, 'name' => $new_feed_name);
        $feeds[] = $new_feed;
        $options['rss_feeds'] = $feeds;
        $save_result = update_option($this->admin->get_option_name(), $options);
        if ($save_result) {
            $html = $this->admin->get_feed_item_html($new_feed);
            wp_send_json_success(array(
                'message' => __('Feed added successfully', 'rss-news-importer'),
                'html' => $html
            ));
        } else {
            wp_send_json_error(__('Failed to save feed', 'rss-news-importer'));
        }
    }

    public function remove_feed_ajax()
    {
        $this->check_ajax_permissions();
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        if (empty($feed_url)) {
            $this->logger->log('RSS Importer: Attempt to remove feed with empty URL', 'error');
            wp_send_json_error('Invalid feed URL');
            exit;
        }
        $options = get_option($this->admin->get_option_name());
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        $this->logger->log('RSS Importer: Removing feed - ' . $feed_url, 'info');
        $this->logger->log('RSS Importer:  fe- ' . print_r($feeds, true), 'debug');
        $feeds = array_filter($feeds, function ($feed) use ($feed_url) {
            if (is_array($feed)) {
                return $feed['url'] !== $feed_url;
            }
            return $feed !== $feed_url;
        });
        $options['rss_feeds'] = array_values($feeds);
        $this->logger->log('RSS Importer: Feeds after removal - ' . print_r($options['rss_feeds'], true), 'debug');
        $update_result = update_option($this->admin->get_option_name(), $options);
        if ($update_result) {
            $this->logger->log('RSS Importer: Feed removed successfully', 'info');
            wp_send_json_success('Feed removed successfully');
        } else {
            $this->logger->log('RSS Importer: Failed to remove feed. Current option value: ' . print_r(get_option($this->admin->get_option_name()), true), 'error');
            wp_send_json_error('Failed to remove feed');
        }
    }

    public function preview_feed_ajax()
    {
        $this->check_ajax_permissions();
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        if (empty($feed_url)) {
            wp_send_json_error('Invalid feed URL');
            exit;
        }
        $parser = new RSS_News_Importer_Parser($this->logger, $this->admin->get_cache());
        $preview_html = $parser->preview_feed($feed_url);
        if (is_wp_error($preview_html)) {
            wp_send_json_error($preview_html->get_error_message());
        } else {
            wp_send_json_success($preview_html);
        }
    }

    public function update_feed_order_ajax()
    {
        $this->check_ajax_permissions();
        $new_order = isset($_POST['order']) ? $_POST['order'] : array();
        if (empty($new_order)) {
            wp_send_json_error(__('Invalid feed order', 'rss-news-importer'));
        }
        $options = get_option($this->admin->get_option_name());
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
        update_option($this->admin->get_option_name(), $options);
        wp_send_json_success(__('Feed order updated successfully', 'rss-news-importer'));
    }




    // 立即导入源
    public function run_cron_now_ajax()
    {
        check_ajax_referer('rss_news_importer_run_cron_now', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'rss-news-importer'));
        }

        $result = $this->cron_manager->manual_update();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'logs' => $result['logs']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    public function save_settings_ajax()
    {
        if (!check_ajax_referer('rss_news_importer_save_settings', 'security', false)) {
            wp_send_json_error('Nonce verification failed.');
            return;
        }
        $options = isset($_POST['rss_news_importer_options']) ? $_POST['rss_news_importer_options'] : array();
        $current_options = get_option('rss_news_importer_options', array());
        $validated_options = RSS_News_Importer_Settings::validate_options($options);
        $diff = $this->array_diff_assoc_recursive($validated_options, $current_options);
        if (!empty($diff)) {
            $update_result = update_option('rss_news_importer_options', $validated_options);
            if ($update_result) {
                wp_send_json_success(__('Settings saved.', 'rss-news-importer'));
            } else {
                wp_send_json_error(__('Failed to save settings.', 'rss-news-importer'));
            }
        } else {
            wp_send_json_success(__('No changes detected.', 'rss-news-importer'));
        }
    }

    private function array_diff_assoc_recursive($array1, $array2)
    {
        $difference = array();
        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }

    public function clear_cache_ajax()
    {
        check_ajax_referer('rss_news_importer_clear_cache', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'rss-news-importer'));
        }

        try {
            $cache = $this->admin->get_cache();
            if (!$cache) {
                throw new Exception('Cache object not initialized');
            }

            $result = $cache->clear_all_cache();

            if ($result) {
                $this->logger->log('Cache cleared successfully', 'info');
                wp_send_json_success(__('All cache cleared.', 'rss-news-importer'));
            } else {
                throw new Exception('Failed to clear cache');
            }
        } catch (Exception $e) {
            $this->logger->log('Failed to clear cache: ' . $e->getMessage(), 'error');
            wp_send_json_error(__('Failed to clear cache: ', 'rss-news-importer') . $e->getMessage());
        }
    }

    public function get_logs_ajax()
    {
        check_ajax_referer('rss_news_importer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        $logger = new RSS_News_Importer_Logger();
        $logs = $logger->get_logs();

        wp_send_json_success($logs);
    }

    public function clear_logs_ajax()
    {
        check_ajax_referer('rss_news_importer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        $logger = new RSS_News_Importer_Logger();
        $result = $logger->clear_logs();

        if ($result) {
            wp_send_json_success('Logs cleared successfully');
        } else {
            wp_send_json_error('Failed to clear logs');
        }
    }
    public function refresh_logs_ajax()
    {
        check_ajax_referer('rss_news_importer_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        $logger = new RSS_News_Importer_Logger();
        $logs = $logger->refresh_logs();

        wp_send_json_success($logs);
    }
}
