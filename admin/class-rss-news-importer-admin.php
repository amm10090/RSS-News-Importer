<?php
// 安全检查
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin
{
    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';

    /**
     * 构造函数
     * 初始化插件名称和版本,并设置必要的WordPress钩子
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->register_ajax_actions();
    }

    /**
     * 注册AJAX动作
     * 为插件的各种功能注册AJAX处理方法
     */
    private function register_ajax_actions()
    {
        $ajax_actions = array(
            'import_now',
            'add_feed',
            'remove_feed',
            'view_logs',
            'preview_feed'
        );

        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_rss_news_importer_' . $action, array($this, $action . '_ajax'));
        }
    }

    /**
     * 加载管理页面样式
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
    }

    /**
     * 加载管理页面脚本
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name, 'rss_news_importer_ajax', $this->get_ajax_data());
    }

    /**
     * 获取AJAX数据
     * 返回用于JavaScript的AJAX相关数据
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
                'error_text' => __('An error occurred. Please try again.', 'rss-news-importer')
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
     * 常规设置部分的回调函数
     */
    public function general_settings_section_cb()
    {
        echo '<p>' . __('Configure your RSS News Importer settings here.', 'rss-news-importer') . '</p>';
    }

    /**
     * RSS源设置字段的回调函数
     */
    public function rss_feeds_cb()
    {
        $options = get_option($this->option_name);
        $rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        ?>
        <div id="rss-feeds-list">
            <?php foreach ($rss_feeds as $index => $feed) : ?>
                <div class="feed-item">
                    <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_url($feed); ?>" class="regular-text" />
                    <button type="button" class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
                    <button type="button" class="button preview-feed" data-feed-url="<?php echo esc_url($feed); ?>"><?php _e('Preview', 'rss-news-importer'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="feed-actions">
            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>" class="regular-text">
            <button type="button" class="button" id="add-feed"><?php _e('Add New Feed', 'rss-news-importer'); ?></button>
        </div>
        <?php
    }

    /**
     * 更新频率设置字段的回调函数
     */
    public function update_frequency_cb()
    {
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

    /**
     * 导入选项设置字段的回调函数
     */
    public function import_options_cb()
    {
        $options = get_option($this->option_name);
        $category = isset($options['import_category']) ? $options['import_category'] : '';
        $author = isset($options['import_author']) ? $options['import_author'] : get_current_user_id();
        ?>
        <p>
            <label><?php _e('Default Category:', 'rss-news-importer'); ?></label>
            <?php
            wp_dropdown_categories(array(
                'name' => $this->option_name . '[import_category]',
                'selected' => $category,
                'show_option_none' => __('Select a category', 'rss-news-importer'),
                'option_none_value' => '',
                'hide_empty' => 0,
            ));
            ?>
        </p>
        <p>
            <label><?php _e('Default Author:', 'rss-news-importer'); ?></label>
            <?php
            wp_dropdown_users(array(
                'name' => $this->option_name . '[import_author]',
                'selected' => $author,
            ));
            ?>
        </p>
        <?php
    }

    /**
     * 缩略图设置字段的回调函数
     */
    public function thumbnail_settings_cb()
    {
        $options = get_option($this->option_name);
        $thumb_size = isset($options['thumb_size']) ? $options['thumb_size'] : 'thumbnail';
        $force_thumb = isset($options['force_thumb']) ? $options['force_thumb'] : 0;
        ?>
        <p>
            <label><?php _e('Thumbnail Size:', 'rss-news-importer'); ?></label>
            <select name="<?php echo $this->option_name; ?>[thumb_size]">
                <?php
                $sizes = get_intermediate_image_sizes();
                foreach ($sizes as $size) {
                    echo '<option value="' . $size . '" ' . selected($thumb_size, $size, false) . '>' . $size . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo $this->option_name; ?>[force_thumb]" value="1" <?php checked($force_thumb, 1); ?>>
                <?php _e('Force thumbnail generation', 'rss-news-importer'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * 导入限制设置字段的回调函数
     */
    public function import_limit_cb()
    {
        $options = get_option($this->option_name);
        $import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;
        ?>
        <input type="number" name="<?php echo $this->option_name; ?>[import_limit]" value="<?php echo esc_attr($import_limit); ?>" min="1" max="100">
        <p class="description"><?php _e('Limit the number of posts to import per feed.', 'rss-news-importer'); ?></p>
        <?php
    }

    /**
     * 内容排除设置字段的回调函数
     */
    public function content_exclusions_cb()
    {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';
        ?>
        <textarea name="<?php echo $this->option_name; ?>[content_exclusions]" rows="4" cols="50"><?php echo esc_textarea($exclusions); ?></textarea>
        <p class="description"><?php _e('Enter CSS selectors or text patterns to exclude, one per line.', 'rss-news-importer'); ?></p>
        <?php
    }

    /**
     * 验证选项
     */
    public function validate_options($input)
    {
        $valid = array();
        $valid['rss_feeds'] = isset($input['rss_feeds']) ? $this->sanitize_rss_feeds($input['rss_feeds']) : array();
        $valid['update_frequency'] = isset($input['update_frequency']) ? sanitize_text_field($input['update_frequency']) : 'hourly';
        $valid['import_category'] = isset($input['import_category']) ? absint($input['import_category']) : 0;
        $valid['import_author'] = isset($input['import_author']) ? absint($input['import_author']) : get_current_user_id();
        $valid['thumb_size'] = isset($input['thumb_size']) ? sanitize_text_field($input['thumb_size']) : 'thumbnail';
        $valid['force_thumb'] = isset($input['force_thumb']) ? 1 : 0;
        $valid['import_limit'] = isset($input['import_limit']) ? intval($input['import_limit']) : 10;
        $valid['content_exclusions'] = isset($input['content_exclusions']) ? sanitize_textarea_field($input['content_exclusions']) : '';
        return $valid;
    }

    /**
     * 清理RSS源URL
     */
    private function sanitize_rss_feeds($feeds)
    {
        return array_map('esc_url_raw', $feeds);
    }

    /**
     * 检查AJAX权限
     */
    private function check_ajax_permissions()
    {
        check_ajax_referer('rss_news_importer_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user', 'rss-news-importer'));
        }
    }

/**
     * 立即导入AJAX处理函数
     */
    public function import_now_ajax()
    {
        $this->check_ajax_permissions();
        $importer = new RSS_News_Importer_Post_Importer();
        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $results = array_map(function($feed) use ($importer) {
            $result = $importer->import_feed($feed);
            return sprintf(__('Imported %d posts from %s', 'rss-news-importer'), $result, $feed);
        }, $feeds);

        wp_send_json_success(implode('<br>', $results));
    }

    /**
     * 添加RSS源AJAX处理函数
     */
    public function add_feed_ajax()
    {
        $this->check_ajax_permissions();
        $new_feed = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($new_feed)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        
        if (!in_array($new_feed, $feeds)) {
            $feeds[] = $new_feed;
            $options['rss_feeds'] = $feeds;
            update_option($this->option_name, $options);
            wp_send_json_success(array(
                'message' => __('Feed added successfully', 'rss-news-importer'),
                'feed_url' => $new_feed
            ));
        } else {
            wp_send_json_error(__('Feed already exists', 'rss-news-importer'));
        }
    }

    /**
     * 移除RSS源AJAX处理函数
     */
    public function remove_feed_ajax()
    {
        $this->check_ajax_permissions();
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
        
        $key = array_search($feed_url, $feeds);
        if ($key !== false) {
            unset($feeds[$key]);
            $options['rss_feeds'] = array_values($feeds);
            update_option($this->option_name, $options);
            wp_send_json_success(__('Feed removed successfully', 'rss-news-importer'));
        } else {
            wp_send_json_error(__('Feed not found', 'rss-news-importer'));
        }
    }

    /**
     * 查看日志AJAX处理函数
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
     * 预览RSS源AJAX处理函数
     */
    public function preview_feed_ajax()
    {
        $this->check_ajax_permissions();
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }

        $rss = fetch_feed($feed_url);

        if (is_wp_error($rss)) {
            wp_send_json_error(__('Error fetching feed', 'rss-news-importer'));
        }

        $maxitems = $rss->get_item_quantity(5);
        $rss_items = $rss->get_items(0, $maxitems);

        $preview_html = '<ul>';
        foreach ($rss_items as $item) {
            $preview_html .= '<li>';
            $preview_html .= '<h3>' . esc_html($item->get_title()) . '</h3>';
            $preview_html .= '<p>' . wp_trim_words($item->get_description(), 55, '...') . '</p>';
            $preview_html .= '</li>';
        }
        $preview_html .= '</ul>';

        wp_send_json_success($preview_html);
    }
}