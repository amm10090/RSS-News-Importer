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
    private $cron_manager;

    // 构造函数：初始化插件并设置钩子
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->option_name = 'rss_news_importer_options';
        $this->cron_manager = new RSS_News_Importer_Cron_Manager($plugin_name, $version);

        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        $this->register_ajax_actions();

        // 检查选项是否存在，如果不存在则创建
        if (false === get_option($this->option_name)) {
            add_option($this->option_name, array());
        }
    }

    // 注册AJAX动作：设置所有AJAX操作的钩子
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
            'clear_logs'


        );

        foreach ($ajax_actions as $action) {
            add_action('wp_ajax_rss_news_importer_' . $action, array($this, $action . '_ajax'));
        }
    }

    // 加载管理页面样式
    public function enqueue_styles($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
        }
    }

    // 加载管理页面脚本
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

    //加载 React 和 ReactDOM 脚本
    private function enqueue_react_scripts()
    {
        wp_enqueue_script('react', 'https://unpkg.com/react@17/umd/react.production.min.js', array(), '17.0.2', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.production.min.js', array('react'), '17.0.2', true);
        wp_enqueue_script('log-viewer-component', plugin_dir_url(__FILE__) . 'js/log-viewer-component.js', array('react', 'react-dom'), $this->version, true);
    }

    // 获取AJAX数据
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

    // 添加插件管理菜单
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
    }

    // 显示插件设置页面
    public function display_plugin_setup_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->handle_settings_update();
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/rss-news-importer-admin-display.php';
    }

    // 显示仪表板页面
    public function display_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $dashboard = new RSS_News_Importer_Dashboard($this->plugin_name, $this->version);
        $dashboard->display_dashboard();
    }

    // 处理设置更新
    private function handle_settings_update()
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('Settings Saved', 'rss-news-importer'), 'updated');
        }
        settings_errors('rss_news_importer_messages');
    }

    // 注册插件设置
    public function register_settings()
    {
        register_setting($this->plugin_name, $this->option_name, array($this, 'validate_options'));
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
    }

    // 添加设置字段
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

    // 通用设置部分回调
    public function general_settings_section_cb()
    {
        echo '<p>' . __('Configure your RSS News Importer settings here.', 'rss-news-importer') . '</p>';
    }

    // RSS源设置字段回调
    public function rss_feeds_cb()
    {
        $options = get_option($this->option_name);
        $rss_feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        if (!is_array($rss_feeds)) {
            $rss_feeds = array();
        }
?>
        <div id="rss-feeds-list" class="sortable-list">
            <?php if (empty($rss_feeds)) : ?>
                <p><?php _e('No RSS feeds added yet.', 'rss-news-importer'); ?></p>
            <?php else : ?>
                <?php foreach ($rss_feeds as $index => $feed) :
                    $feed_url = is_array($feed) ? $feed['url'] : $feed;
                    $feed_name = is_array($feed) && isset($feed['name']) ? $feed['name'] : '';
                ?>
                    <div class="feed-item" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                        <span class="dashicons dashicons-menu handle"></span>
                        <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][<?php echo $index; ?>][url]" value="<?php echo esc_url($feed_url); ?>" readonly class="feed-url">
                        <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][<?php echo $index; ?>][name]" value="<?php echo esc_attr($feed_name); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
                        <button type="button" class="button remove-feed"><?php _e('Remove', 'rss-news-importer'); ?></button>
                        <button type="button" class="button preview-feed"><?php _e('Preview', 'rss-news-importer'); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="feed-actions">
            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>" class="regular-text">
            <input type="text" id="new-feed-name" placeholder="<?php _e('Enter feed name (optional)', 'rss-news-importer'); ?>" class="regular-text">
            <button type="button" class="button" id="add-feed"><?php _e('Add New Feed', 'rss-news-importer'); ?></button>
        </div>
        <div id="feed-preview"></div>
    <?php
    }

    // 更新频率设置字段回调
    public function update_frequency_cb()
    {
        $options = get_option($this->option_name);
        $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly';
    ?>
        <select name="<?php echo $this->option_name; ?>[update_frequency]">
            <option value="hourly" <?php selected($frequency, 'hourly'); ?>><?php _e('Hourly', 'rss-news-importer'); ?></option>
            <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>><?php _e('Twice Daily', 'rss-news-importer'); ?></option>
            <option value="daily" <?php selected($frequency, 'daily'); ?>><?php _e('Daily', 'rss-news-importer'); ?></option>
            <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php _e('Weekly', 'rss-news-importer'); ?></option>
        </select>
    <?php
        $next_run = $this->cron_manager->get_next_scheduled_time();
        echo '<p>' . sprintf(__('Next scheduled run: %s', 'rss-news-importer'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run)) . '</p>';
    }

    // 导入选项设置字段回调
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

    // 缩略图设置字段回调
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

    // 导入限制设置字段回调
    public function import_limit_cb()
    {
        $options = get_option($this->option_name);
        $import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;
    ?>
        <input type="number" name="<?php echo $this->option_name; ?>[import_limit]" value="<?php echo esc_attr($import_limit); ?>" min="1" max="100">
        <p class="description"><?php _e('Limit the number of posts to import per feed.', 'rss-news-importer'); ?></p>
    <?php
    }

    // 内容排除设置字段回调
    public function content_exclusions_cb()
    {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';
    ?>
        <textarea name="<?php echo $this->option_name; ?>[content_exclusions]" rows="4" cols="50"><?php echo esc_textarea($exclusions); ?></textarea>
        <p class="description"><?php _e('Enter CSS selectors or text patterns to exclude, one per line.', 'rss-news-importer'); ?></p>
<?php
    }

    // 验证选项
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

        // 添加调试日志
        error_log('Validating options: ' . print_r($input, true));
        error_log('Validated options: ' . print_r($valid, true));

        return $valid;
    }

    // 清理RSS源数据
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
        error_log('Sanitized RSS feeds: ' . print_r($sanitized_feeds, true));
        return $sanitized_feeds;
    }

    // 检查AJAX权限
    private function check_ajax_permissions()
    {
        check_ajax_referer('rss_news_importer_nonce', 'security');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized user', 'rss-news-importer'));
        }
    }

    // 立即导入AJAX处理函数
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

    // 添加RSS源AJAX处理函数
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

        // 确保 $feeds 是一个数组
        if (!is_array($feeds)) {
            $feeds = array();
        }

        $new_feed = array('url' => $new_feed_url, 'name' => $new_feed_name);
        $feeds[] = $new_feed;
        $options['rss_feeds'] = $feeds;

        $save_result = update_option($this->option_name, $options);

        if ($save_result) {
            wp_send_json_success(array(
                'message' => __('Feed added successfully', 'rss-news-importer'),
                'feed_url' => $new_feed_url,
                'feed_name' => $new_feed_name
            ));
        } else {
            wp_send_json_error(__('Failed to save feed', 'rss-news-importer'));
        }
    }

    // 移除RSS源AJAX处理函数
    public function remove_feed_ajax()
    {
        $this->check_ajax_permissions();
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }

        $options = get_option($this->option_name);
        $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();

        $feeds = array_filter($feeds, function ($feed) use ($feed_url) {
            return (is_array($feed) && $feed['url'] !== $feed_url) || $feed !== $feed_url;
        });

        $options['rss_feeds'] = array_values($feeds);
        update_option($this->option_name, $options);
        wp_send_json_success(__('Feed removed successfully', 'rss-news-importer'));
    }

    // 查看日志AJAX处理函数
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

    // 预览RSS源AJAX处理函数
    public function preview_feed_ajax()
    {
        $this->check_ajax_permissions();
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';

        if (empty($feed_url)) {
            wp_send_json_error(__('Invalid feed URL', 'rss-news-importer'));
        }

        $parser = new RSS_News_Importer_Parser();
        $feed_data = $parser->fetch_feed($feed_url);

        if (!$feed_data) {
            wp_send_json_error(__('Error fetching feed', 'rss-news-importer'));
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
    }

    // 更新RSS源顺序的AJAX处理函数
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
    //获取日志数据并返回给前端
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
    //日志位置
    private function get_log_file_path()
    {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/rss-news-importer-log.txt';
    }
    //获取日志
    public function get_logs($limit = 100)
    {
        $log_file = $this->get_log_file_path();
        if (!file_exists($log_file)) {
            return array();
        }

        $logs = array();
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        $lines = array_reverse($lines);
        $count = 0;

        foreach ($lines as $line) {
            if ($count >= $limit) {
                break;
            }

            $log_entry = json_decode($line, true);
            if ($log_entry) {
                $logs[] = $log_entry;
                $count++;
            }
        }

        return $logs;
    }
    //删除日志
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
}
