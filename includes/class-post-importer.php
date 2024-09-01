<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Post_Importer
{
    // 插件名称
    private $plugin_name;

    // 插件版本
    private $version;

    // RSS解析器实例
    private $parser;

    // 日志记录器实例
    private $logger;

    // 缓存实例
    private $cache;
    private $option_name = 'rss_news_importer_options';

    // 图片抓取器实例
    private $image_scraper;

    /**
     * 构造函数：初始化导入器
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logger = new RSS_News_Importer_Logger();
        $this->cache = new RSS_News_Importer_Cache($this->plugin_name, $this->version);
        $this->parser = new RSS_News_Importer_Parser($this->logger, $this->cache);
    }

    /**
     * 设置图片抓取器
     *
     * @param object $scraper 图片抓取器实例
     */
    public function set_image_scraper($scraper)
    {
        $this->image_scraper = $scraper;
    }

    /**
     * 导入RSS源
     *
     * @param string $url RSS源URL
     * @return int|bool 导入的文章数量，失败时返回false
     */
    public function import_feed($url)
{
    $options = get_option($this->option_name);
    $import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;

    // 首先检查缓存
    $cached_data = $this->cache->get_cached_feed($url);
    if ($cached_data !== false) {
        $this->logger->log("Using cached data for feed: $url", 'info');
        return $this->process_feed_data($cached_data, $url, $import_limit);
    }

    // 使用条件GET获取源
    $rss_items = $this->parser->fetch_feed($url);

    if (is_wp_error($rss_items)) {
        $this->logger->log("Failed to fetch or parse feed: $url - " . $rss_items->get_error_message(), 'error');
        return false;
    }

    if ($rss_items === 'not_modified') {
        $this->logger->log("RSS源未修改: $url", 'info');
        return 0;
    }

    if (!is_array($rss_items)) {
        $this->logger->log("Fetched feed is not an array: $url", 'error');
        return false;
    }

    // 缓存新数据
    $this->cache->set_cached_feed($url, $rss_items);
    
    try {
        return $this->process_feed_data($rss_items, $url, $import_limit);
    } catch (Exception $e) {
        $this->logger->log("导入RSS源失败: " . $e->getMessage(), 'error');
        return false;
    }
}

    /**
     * 处理源数据
     *
     * @param array $rss_items RSS项目数组
     * @param string $url RSS源URL
     * @param int $import_limit 导入限制
     * @return int 导入的文章数量
     */
private function process_feed_data($rss_items, $url, $import_limit)
{
    if (!is_array($rss_items)) {
        $this->logger->log("RSS items is not an array for feed: $url", 'error');
        return 0;
    }

    $imported_count = 0;
    $skipped_count = 0;

    foreach (array_slice($rss_items, 0, $import_limit) as $item) {
        $result = $this->import_item($item);
        if ($result === true) {
            $imported_count++;
        } elseif ($result === 'skipped') {
            $skipped_count++;
        }
    }

    $this->logger->log("从 $url 导入了 $imported_count 篇文章 (跳过了 $skipped_count 篇重复文章)", 'info');

    return $imported_count;
}

/**
 * 导入单个项目
 *
 * @param array $item RSS项目
 * @return bool|string true表示成功导入，'skipped'表示跳过，false表示失败
 */
private function import_item($item)
{
    // 检查必要的键是否存在
    if (!isset($item['title']) || !isset($item['link'])) {
        $this->logger->log("RSS项目缺少必要的字段", 'error');
        return false;
    }

    $guid = isset($item['guid']) ? $item['guid'] : $item['link'];
    if ($this->post_exists($guid)) {
        $this->logger->log("跳过重复项目: " . $item['title'], 'info');
        return 'skipped';
    }

    $post_content = isset($item['content']) ? $item['content'] : (isset($item['description']) ? $item['description'] : '');
    $post_content = $this->filter_content($post_content);

    $options = get_option($this->option_name);
    $post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';

    $post_data = array(
        'post_title'    => wp_strip_all_tags($item['title']),
        'post_content'  => $post_content,
        'post_excerpt'  => isset($item['description']) ? wp_trim_words($item['description'], 55, '...') : '',
        'post_date'     => isset($item['pubDate']) ? date('Y-m-d H:i:s', strtotime($item['pubDate'])) : current_time('mysql'),
        'post_status'   => $post_status,
        'post_author'   => $this->get_default_author(),
        'post_type'     => 'post',
        'post_category' => $this->get_default_category(),
        'meta_input'    => array(
            'rss_news_importer_guid' => $guid,
            'rss_news_importer_link' => $item['link'],
            'rss_news_importer_author' => isset($item['author']) ? $item['author'] : '',
            'rss_news_importer_import_date' => current_time('mysql'),
        ),
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        $this->logger->log("导入项目失败: " . $item['title'] . ". 错误: " . $post_id->get_error_message(), 'error');
        add_post_meta(0, 'rss_news_importer_import_failed', $item['title'], false);
        return false;
    }

    $this->set_post_thumbnail($post_id, $item);
    $this->set_post_tags($post_id, $item);

    $this->logger->log("成功导入文章: " . $item['title'], 'info');
    return true;
}

    /**
     * 设置文章缩略图
     *
     * @param int $post_id 文章ID
     * @param array $item RSS项目
     */
    private function set_post_thumbnail($post_id, $item)
    {
        $thumbnail_id = $this->set_featured_image($post_id, $item['thumbnail'], $item['title']);
        
        if (!$thumbnail_id) {
            $first_image = $this->get_first_image_from_content($item['content']);
            if ($first_image) {
                $thumbnail_id = $this->set_featured_image($post_id, $first_image, $item['title']);
            }
        }

        if (!$thumbnail_id && $this->image_scraper) {
            $scraped_image_url = $this->image_scraper->scrape_image($item['link'], $item['title']);
            if ($scraped_image_url) {
                $thumbnail_id = $this->set_featured_image($post_id, $scraped_image_url, $item['title']);
            }
        }

        if ($thumbnail_id) {
            update_post_meta($post_id, 'rss_news_importer_cover_image', wp_get_attachment_url($thumbnail_id));
        }
    }

    /**
     * 设置文章标签
     *
     * @param int $post_id 文章ID
     * @param array $item RSS项目
     */
    private function set_post_tags($post_id, $item)
    {
        if (!empty($item['categories'])) {
            wp_set_post_tags($post_id, $item['categories'], true);
        }
    }

    /**
     * 检查文章是否已存在
     *
     * @param string $guid 全局唯一标识符
     * @return bool 文章是否存在
     */
    private function post_exists($guid)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rss_news_importer_guid' AND meta_value=%s", $guid));
    }

    /**
     * 获取默认作者
     *
     * @return int 默认作者ID
     */
    private function get_default_author()
    {
        $options = get_option($this->option_name);
        return isset($options['import_author']) ? intval($options['import_author']) : 1;
    }

    /**
     * 获取默认分类
     *
     * @return array 默认分类ID数组
     */
    private function get_default_category()
    {
        $options = get_option($this->option_name);
        $category_id = isset($options['import_category']) ? intval($options['import_category']) : 1;
        return array($category_id);
    }

    /**
     * 设置特色图片
     *
     * @param int $post_id 文章ID
     * @param string $image_url 图片URL
     * @param string $title 文章标题
     * @return int|false 附件ID，失败时返回false
     */
    private function set_featured_image($post_id, $image_url, $title)
    {
        if (!$image_url) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);

        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        set_post_thumbnail($post_id, $attach_id);

        return $attach_id;
    }

    /**
     * 从内容中获取第一张图片
     *
     * @param string $content 文章内容
     * @return string|false 图片URL，未找到时返回false
     */
    private function get_first_image_from_content($content)
    {
        preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $image);
        return isset($image['src']) ? $image['src'] : false;
    }

    /**
     * 过滤内容
     *
     * @param string $content 原始内容
     * @return string 过滤后的内容
     */
    private function filter_content($content)
    {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';
        $convert_links = isset($options['convert_links']) ? $options['convert_links'] : '';

        if (empty($exclusions) && empty($convert_links) && !has_filter('rss_news_importer_filter_content')) {
            return $content;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        if (!empty($convert_links)) {
            $links = $xpath->query('//a');
            foreach ($links as $link) {
                $new_element = $dom->createElement($convert_links);
                while ($link->firstChild) {
                    $new_element->appendChild($link->firstChild);
                }
                $link->parentNode->replaceChild($new_element, $link);
            }
        }

        if (!empty($exclusions)) {
            $exclusions = explode("\n", $exclusions);
            foreach ($exclusions as $exclusion) {
                $exclusion = trim($exclusion);
                if (empty($exclusion)) continue;

                if (strpos($exclusion, '#') === 0 || strpos($exclusion, '.') === 0) {
                    $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' " . substr($exclusion, 1) . " ')] | //*[@id='" . substr($exclusion, 1) . "']");
                } elseif (strpos($exclusion, '/') === 0) {
                    $nodes = $xpath->query($exclusion);
                } else {
                    $nodes = $xpath->query("//*[contains(text(), '$exclusion')]");
                }

                foreach ($nodes as $node) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $content = $dom->saveHTML();

        return apply_filters('rss_news_importer_filter_content', $content);
    }

    /**
     * 净化HTML内容
     *
     * @param string $html HTML内容
     * @return string 净化后的HTML
     */
    private function sanitize_html($html)
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $html);
        $html = preg_replace('/on\w+="[^"]*"/', '', $html);
        $html = preg_replace('/style="[^"]*"/', '', $html);

        $allowed_html = array(
            'a' => array('href' => array(), 'title' => array()),
            'p' => array(),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'h1' => array(),
            'h2' => array(),
            'h3' => array(),
            'h4' => array(),
            'h5' => array(),
            'h6' => array(),
            'img' => array('src' => array(), 'alt' => array(), 'width' => array(), 'height' => array()),
        );

        return wp_kses($html, $allowed_html);
    }

    /**
     * 获取特定时期的导入数量
     *
     * @param string $period 时期（'today' 或 'week'）
     * @return int 导入数量
     */
    public function get_imports_count_for_period($period) {
        global $wpdb;
        $date = '';
        switch ($period) {
            case 'today':
                $date = date('Y-m-d');
                break;
            case 'week':
                $date = date('Y-m-d', strtotime('-1 week'));
                break;
            default:
                return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date' 
            AND meta_value >= %s",
            $date
        ));

        return intval($count);
    }

    /**
     * 获取总导入数量
     *
     * @return int 总导入数量
     */
    public function get_total_imports_count() {
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date'"
        );
        return intval($count);
    }

    /**
     * 获取最后导入时间
     *
     * @return string 最后导入时间
     */
    public function get_last_import_time() {
        global $wpdb;
        $last_import = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date' 
            ORDER BY meta_id DESC 
            LIMIT 1"
        );
        return $last_import ? $last_import : __('Never', 'rss-news-importer');
    }

    /**
     * 获取RSS源列表
     *
     * @return array RSS源列表
     */
    public function get_rss_feeds() {
        $options = get_option($this->option_name);
        return isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
    }

    /**
     * 获取平均导入时间
     *
     * @return float 平均导入时间（秒）
     */
    public function get_average_import_time() {
        global $wpdb;
        $avg_time = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, p.post_date, pm.meta_value)) 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE pm.meta_key = 'rss_news_importer_import_date'"
        );
        return round(floatval($avg_time), 2);
    }

    /**
     * 获取每小时导入数量
     *
     * @return int 每小时导入数量
     */
    public function get_imports_per_hour() {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date' 
            AND meta_value >= %s",
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));
        return intval($count);
    }

    /**
     * 获取每天导入数量
     *
     * @return int 每天导入数量
     */
    public function get_imports_per_day() {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = 'rss_news_importer_import_date' 
            AND meta_value >= %s",
            date('Y-m-d H:i:s', strtotime('-1 day'))
        ));
        return intval($count);
    }

    /**
     * 获取最近的导入
     *
     * @param int $limit 限制数量
     * @return array 最近的导入列表
     */
    public function get_recent_imports($limit = 10) {
        global $wpdb;
        $recent_imports = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm.meta_value as import_date 
            FROM {$wpdb->posts} p 
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE pm.meta_key = 'rss_news_importer_import_date' 
            ORDER BY pm.meta_value DESC 
            LIMIT %d",
            $limit
        ));

        return array_map(function($import) {
            return array(
                'id' => $import->ID,
                'title' => $import->post_title,
                'import_date' => $import->import_date
            );
        }, $recent_imports);
    }
    //成功导入数量
    public function get_successful_imports_count() {
    global $wpdb;
    $count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
        WHERE meta_key = 'rss_news_importer_import_date'"
    );
    return intval($count);
}
//获取失败导入计数
public function get_failed_imports_count() {
    global $wpdb;
    $count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
        WHERE meta_key = 'rss_news_importer_import_failed'"
    );
    return intval($count);
}
}