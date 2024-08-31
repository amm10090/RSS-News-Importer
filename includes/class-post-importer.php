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

    // 仪表板实例
    private $dashboard;

    // 选项名称
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
        $this->parser = new RSS_News_Importer_Parser();
        $this->logger = new RSS_News_Importer_Logger();
        $this->cache = new RSS_News_Importer_Cache($this->plugin_name, $this->version);
    }

    /**
     * 设置仪表板实例
     *
     * @param RSS_News_Importer_Dashboard $dashboard 仪表板实例
     */
    public function set_dashboard(RSS_News_Importer_Dashboard $dashboard)
    {
        $this->dashboard = $dashboard;
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

        if ($rss_items === 'not_modified') {
            $this->logger->log("Feed not modified: $url", 'info');
            $this->dashboard->update_feed_status($url, true); // 视为成功
            return 0;
        }

        if (!$rss_items) {
            $this->logger->log("Failed to fetch or parse feed: $url", 'error');
            $this->dashboard->update_feed_status($url, false);
            return false;
        }

        // 缓存新数据
        $this->cache->set_cached_feed($url, $rss_items);

        return $this->process_feed_data($rss_items, $url, $import_limit);
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

        $this->logger->log("Imported $imported_count items from $url (Skipped $skipped_count duplicates)", 'info');
        $this->dashboard->update_feed_status($url, $imported_count > 0);
        $this->dashboard->update_import_statistics($imported_count);

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
        $guid = $item['guid'] ?: $item['link'];
        if ($this->post_exists($guid)) {
            $this->logger->log("Skipped duplicate item: " . $item['title'], 'info');
            return 'skipped';
        }

        $post_content = $this->filter_content($item['content'] ?: $item['description']);
        $options = get_option($this->option_name);
        $post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';
        $post_data = array(
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => $post_content,
            'post_excerpt'  => wp_trim_words($item['description'], 55, '...'),
            'post_date'     => date('Y-m-d H:i:s', strtotime($item['pubDate'])),
            'post_status'   => $post_status,
            'post_author'   => $this->get_default_author(),
            'post_type'     => 'post',
            'post_category' => $this->get_default_category(),
            'meta_input'    => array(
                'rss_news_importer_guid' => $guid,
                'rss_news_importer_link' => $item['link'],
                'rss_news_importer_author' => $item['author'],
            ),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->logger->log("Failed to import item: " . $item['title'], 'error');
            return false;
        }

        $this->set_post_thumbnail($post_id, $item);
        $this->set_post_tags($post_id, $item);

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
}