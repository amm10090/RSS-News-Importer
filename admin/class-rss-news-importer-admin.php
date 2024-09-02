<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 引入必要的类文件
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-admin-ajax.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-dashboard-manager.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-menu.php';
require_once plugin_dir_path(__FILE__) . 'class-rss-news-importer-settings.php';

// 引入 partials 文件
require_once plugin_dir_path(__FILE__) . 'partials/class-rss-news-importer-dashboard.php';

// 引入必要的 includes 类文件
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-content-filter.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-cron-manager.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-image-scraper.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-logger.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-post-importer.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-queue.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-news-importer-cache.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-rss-parser.php';

/**
 * RSS新闻导入器管理类
 */
class RSS_News_Importer_Admin
{
    private $plugin_name;
    private $version;
    private $cron_manager;
    private $logger;
    private $importer;
    private $settings;
    private $option_name = 'rss_news_importer_options';
    private $dashboard;
    private $dashboard_manager;
    private $ajax_handler;
    private $queue_manager;
    private $cache;
    private $menu;
    private $content_filter;
    private $image_scraper;
    private $queue;

    /**
     * 构造函数
     * 
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // 初始化各个组件
        $this->cron_manager = new RSS_News_Importer_Cron_Manager($plugin_name, $version);
        $this->logger = new RSS_News_Importer_Logger();
        $this->cache = new RSS_News_Importer_Cache($plugin_name, $version, $this->logger);
        $this->importer = new RSS_News_Importer_Post_Importer($plugin_name, $version, $this->cache);
        $this->queue = new RSS_News_Importer_Queue($this->logger, $this->cache);
        $this->ajax_handler = new RSS_News_Importer_Admin_Ajax($this);
        $this->settings = new RSS_News_Importer_Settings($plugin_name, $version, $this->option_name, $this);
        $this->menu = new RSS_News_Importer_Menu($plugin_name, $version, $this);
        $this->dashboard_manager = new RSS_News_Importer_Dashboard_Manager(
            $this->importer,
            $this->queue,
            $this->logger,
            $this->cache,
            $this->cron_manager,
            $this->ajax_handler

        );
        $this->dashboard = new RSS_News_Importer_Dashboard($this->dashboard_manager);
        $this->content_filter = new RSS_News_Importer_Content_Filter($this->logger);
        $this->image_scraper = new RSS_News_Importer_Image_Scraper($this->logger);

        // 初始化钩子
        $this->init_hooks();
    }

    /**
     * 初始化钩子
     */
    private function init_hooks()
    {
        // 注册设置
        add_action('admin_init', array($this->settings, 'register_settings'));

        // 添加定时任务执行钩子
        add_action($this->cron_manager->get_cron_hook(), array($this->cron_manager, 'execute_rss_update'));

        // 加载管理页面样式和脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // 添加设置链接
        add_filter('plugin_action_links_' . plugin_basename(RSS_NEWS_IMPORTER_PLUGIN_DIR . 'rss-news-importer.php'), array($this, 'add_settings_link'));

        // 如果选项不存在，则创建
        if (false === get_option($this->option_name)) {
            add_option($this->option_name, array());
        }
    }

    /**
     * 加载管理页面样式
     * 
     * @param string $hook 当前WordPress页面的钩子后缀
     */
    public function enqueue_styles($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/rss-news-importer-admin.css', array(), $this->version, 'all');
            wp_enqueue_style(
                'rss-news-importer-dashboard',
                plugin_dir_url(__FILE__) . 'css/rss-news-importer-dashboard.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * 加载管理页面脚本
     * 
     * @param string $hook 当前WordPress页面的钩子后缀
     */
    public function enqueue_scripts($hook)
    {
        if (strpos($hook, $this->plugin_name) !== false) {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/rss-news-importer-admin.js', array('jquery', 'jquery-ui-sortable'), $this->version, false);
            wp_localize_script($this->plugin_name, 'rss_news_importer_ajax', $this->get_ajax_data());
            $this->enqueue_react_scripts();
        }
    }

    /**
     * 加载React脚本
     */
    private function enqueue_react_scripts()
    {
        wp_enqueue_script('react', 'https://unpkg.com/react@17.0.2/umd/react.production.min.js', array(), '17.0.2', false);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@17.0.2/umd/react-dom.production.min.js', array('react'), '17.0.2', false);
        wp_enqueue_script('log-viewer-component', plugin_dir_url(__FILE__) . 'js/log-viewer-component.js', array('react', 'react-dom'), $this->version, false);
    }

    /**
     * 获取AJAX数据
     * 
     * @return array AJAX数据
     */
    private function get_ajax_data()
    {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rss_news_importer_nonce'),
            'i18n' => array(
                'add_feed_prompt' => __('请输入要添加的RSS源URL:', 'rss-news-importer'),
                'remove_text' => __('移除', 'rss-news-importer'),
                'importing_text' => __('导入中...', 'rss-news-importer'),
                'error_text' => __('发生错误。请重试。', 'rss-news-importer'),
                'running_text' => __('运行中...', 'rss-news-importer'),
                'run_now_text' => __('立即运行', 'rss-news-importer'),
                'save_settings_nonce' => wp_create_nonce('rss_news_importer_save_settings')
            )
        );
    }

    /**
     * 处理设置更新
     */
    public function handle_settings_update()
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('设置已保存', 'rss-news-importer'), 'updated');
        }
        settings_errors('rss_news_importer_messages');
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
     * 获取RSS源项目的HTML
     * 
     * @param array $feed RSS源数据
     * @return string HTML内容
     */
    public function get_feed_item_html($feed)
    {
        ob_start();
?>
        <div class="feed-item" data-feed-url="<?php echo esc_attr($feed['url']); ?>">
            <span class="dashicons dashicons-menu handle"></span>
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_url($feed['url']); ?>" readonly class="feed-url">
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][]" value="<?php echo esc_attr($feed['name']); ?>" placeholder="<?php _e('源名称（可选）', 'rss-news-importer'); ?>" class="feed-name">
            <button class="button remove-feed"><?php _e('移除', 'rss-news-importer'); ?></button>
            <button class="button preview-feed"><?php _e('预览', 'rss-news-importer'); ?></button>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * 添加设置链接到插件页面
     * 
     * @param array $links 现有的插件链接
     * @return array 修改后的插件链接
     */
    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=' . $this->plugin_name) . '">' . __('设置', 'rss-news-importer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * 准备仪表板
     */
    public function prepare_dashboard()
    {
        // 仪表板已经在构造函数中初始化，此方法可以用于其他准备工作
    }

    // Getter 方法
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    public function get_version()
    {
        return $this->version;
    }

    public function get_option_name()
    {
        return $this->option_name;
    }

    public function get_cache()
    {
        return $this->cache;
    }

    public function get_queue_manager()
    {
        return $this->queue_manager;
    }

    public function get_cron_manager()
    {
        return $this->cron_manager;
    }

    public function get_logger()
    {
        return $this->logger;
    }

    public function get_dashboard()
    {
        return $this->dashboard;
    }

    public function get_importer()
    {
        return $this->importer;
    }

    public function get_settings()
    {
        return $this->settings;
    }

    public function get_menu()
    {
        return $this->menu;
    }

    public function get_content_filter()
    {
        return $this->content_filter;
    }

    public function get_image_scraper()
    {
        return $this->image_scraper;
    }
}
