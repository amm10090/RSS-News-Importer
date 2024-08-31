<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Admin_Settings
{
    private $plugin_name;
    private $version;
    private $option_name = 'rss_news_importer_options';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    // 初始化钩子
    public function init_hooks()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    // 注册设置
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
            'import_options' => __('Import Options', 'rss-news-importer'),
            'thumbnail_settings' => __('Thumbnail Settings', 'rss-news-importer'),
            'import_limit' => __('Import Limit', 'rss-news-importer'),
            'content_exclusions' => __('Content Exclusions', 'rss-news-importer'),
            'post_status' => __('Post Status', 'rss-news-importer'),
            'update_frequency' => __('Update Frequency', 'rss-news-importer')
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

    // 常规设置部分回调
    public function general_settings_section_cb()
    {
        echo '<p>' . __('Configure your RSS News Importer settings here.', 'rss-news-importer') . '</p>';
    }

    // 导入选项回调
    public function import_options_cb()
    {
        $options = get_option($this->option_name);
        $category = isset($options['import_category']) ? $options['import_category'] : '';
        $author = isset($options['import_author']) ? $options['import_author'] : get_current_user_id();

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/import-options.php';
    }

    // 缩略图设置回调
    public function thumbnail_settings_cb()
    {
        $options = get_option($this->option_name);
        $thumb_size = isset($options['thumb_size']) ? $options['thumb_size'] : 'thumbnail';
        $force_thumb = isset($options['force_thumb']) ? $options['force_thumb'] : 0;

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/thumbnail-settings.php';
    }

    // 导入限制回调
    public function import_limit_cb()
    {
        $options = get_option($this->option_name);
        $import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/import-limit.php';
    }

    // 内容排除回调
    public function content_exclusions_cb()
    {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/content-exclusions.php';
    }

    // 文章状态回调
    public function post_status_cb()
    {
        $options = get_option($this->option_name);
        $post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/post-status.php';
    }

    // 更新频率回调
    public function update_frequency_cb()
    {
        $options = get_option($this->option_name);
        $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'hourly';

        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/update-frequency.php';
    }

    // 验证选项
    public function validate_options($input)
    {
        $valid = array();

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

        // 验证文章状态
        $valid['post_status'] = isset($input['post_status'])
            ? sanitize_text_field($input['post_status'])
            : 'draft';

        // 验证更新频率
        $valid['update_frequency'] = isset($input['update_frequency'])
            ? sanitize_text_field($input['update_frequency'])
            : 'hourly';

        return $valid;
    }

    // 处理定时任务设置保存
    public function handle_cron_settings_save()
    {
        if (isset($_POST['rss_news_importer_cron_schedule'])) {
            $new_schedule = sanitize_text_field($_POST['rss_news_importer_cron_schedule']);
            $cron_manager = new RSS_News_Importer_Cron_Manager($this->plugin_name, $this->version);
            $cron_manager->update_schedule($new_schedule);
        }
    }
}