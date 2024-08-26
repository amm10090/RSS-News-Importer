<?php
// 安全检查
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin {

    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        // 移除了直接添加动作的代码
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name, 'rss_news_importer_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rss_news_importer_nonce')
        ));
    }

    public function rss_news_importer_general_cb() {
        echo '<p>' . __('General settings for RSS News Importer.', 'rss-news-importer') . '</p>';
    }

    public function add_plugin_admin_menu() {
        error_log('RSS News Importer: add_plugin_admin_menu called'); // 调试代码

        // 添加主菜单项
        add_menu_page(
            __('RSS News Importer', 'rss-news-importer'),
            __('RSS News Importer', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_dashboard_page'),
            'dashicons-rss',
            100
        );
    
        // 添加设置子菜单
        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'rss-news-importer'),
            __('Settings', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name,  // 使用与主菜单相同的 slug
            array($this, 'display_plugin_settings_page')
        );

        // 添加测试 RSS 解析器子菜单
        add_submenu_page(
            $this->plugin_name,
            __('Test RSS Parser', 'rss-news-importer'),
            __('Test RSS Parser', 'rss-news-importer'),
            'manage_options',
            $this->plugin_name . '-test-parser',
            array($this, 'test_rss_parser')
        );
    }

    public function display_plugin_dashboard_page() {
        // 确保用户有权限
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 包含仪表盘显示文件
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-dashboard.php';
    }
    
    public function display_plugin_settings_page() {
        // 确保用户有权限
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 如果设置已更新,显示成功消息
        if (isset($_GET['settings-updated'])) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('Settings Saved', 'rss-news-importer'), 'updated');
        }
        
        // 显示设置错误/更新消息
        settings_errors('rss_news_importer_messages');
        
        // 包含设置页面显示文件
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-settings.php';
    }

    public function register_settings() {
        register_setting($this->plugin_name, $this->option_name, array($this, 'validate_options'));

        add_settings_section(
            'rss_news_importer_general',
            __('General Settings', 'rss-news-importer'),
            array($this, 'rss_news_importer_general_cb'),
            $this->plugin_name
        );

        add_settings_field(
            'rss_feeds',
            __('RSS Feeds', 'rss-news-importer'),
            array($this, 'rss_feeds_cb'),
            $this->plugin_name,
            'rss_news_importer_general'
        );

        add_settings_field(
            'update_frequency',
            __('Update Frequency', 'rss-news-importer'),
            array($this, 'update_frequency_cb'),
            $this->plugin_name,
            'rss_news_importer_general'
        );

        add_settings_field(
            'post_status',
            __('Default Post Status', 'rss-news-importer'),
            array($this, 'post_status_cb'),
            $this->plugin_name,
            'rss_news_importer_general'
        );
    }

    public function validate_options($input) {
        $valid = array();
        $valid['rss_feeds'] = isset($input['rss_feeds']) ? $this->sanitize_rss_feeds($input['rss_feeds']) : array();
        $valid['update_frequency'] = isset($input['update_frequency']) ? sanitize_text_field($input['update_frequency']) : 'hourly';
        $valid['post_status'] = isset($input['post_status']) ? sanitize_text_field($input['post_status']) : 'draft';
        return $valid;
    }

    public function rss_feeds_cb() {
        $options = get_option($this->option_name);
        $rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        ?>
        <div id="rss-feeds">
            <?php foreach ($rss_feeds as $index => $feed) : ?>
                <p>
                    <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_url($feed); ?>" class="regular-text" />
                    <button type="button" class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
                </p>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="add-feed"><?php _e('Add New Feed', 'rss-news-importer'); ?></button>
        <?php
    }

    public function update_frequency_cb() {
        $options = get_option($this->option_name);
        $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly';
        ?>
        <select name="<?php echo $this->option_name; ?>[update_frequency]">
            <option value="hourly" <?php selected($frequency, 'hourly'); ?>><?php _e('Hourly', 'rss-news-importer'); ?></option>
            <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>><?php _e('Twice Daily', 'rss-news-importer'); ?></option>
            <option value="daily" <?php selected($frequency, 'daily'); ?>><?php _e('Daily', 'rss-news-importer'); ?></option>
        </select>
        <?php
    }

    public function post_status_cb() {
        $options = get_option($this->option_name);
        $status = isset($options['post_status']) ? $options['post_status'] : 'draft';
        ?>
        <select name="<?php echo $this->option_name; ?>[post_status]">
            <option value="draft" <?php selected($status, 'draft'); ?>><?php _e('Draft', 'rss-news-importer'); ?></option>
            <option value="publish" <?php selected($status, 'publish'); ?>><?php _e('Published', 'rss-news-importer'); ?></option>
        </select>
        <?php
    }

    public function import_now_ajax() {
        check_ajax_referer('rss_news_importer_nonce', 'security');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user', 'rss-news-importer'));
        }
    
        $importer = new RSS_News_Importer_Post_Importer();
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        
        $results = array();
        foreach ($feeds as $feed) {
            $result = $importer->import_feed($feed);
            $results[] = sprintf(__('Imported %d posts from %s', 'rss-news-importer'), $result, $feed);
        }
    
        wp_send_json_success(implode('<br>', $results));
    }

    private function sanitize_rss_feeds($feeds) {
        $sanitized_feeds = array();
        foreach ($feeds as $feed) {
            $sanitized_feeds[] = esc_url_raw($feed);
        }
        return $sanitized_feeds;
    }
    public function test_rss_parser() {
        $parser = new RSS_News_Importer_Parser();
        $feed_url = 'https://example.com/rss-feed'; // 替换为实际的RSS feed URL
        $items = $parser->fetch_feed($feed_url);
    
        if ($items === false) {
            echo "Failed to fetch or parse the feed.";
            return;
        }
    
        echo "<h2>Parsed RSS Items:</h2>";
        echo "<pre>";
        print_r($items);
        echo "</pre>";
    }
}