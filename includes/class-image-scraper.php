<?php
if (!defined('ABSPATH')) {
    exit;
}

// 如果没有安装 Simple HTML DOM Parser，请先安装它
// 可以通过 Composer 安装或直接下载文件
if (!class_exists('simple_html_dom')) {
    require_once plugin_dir_path(__FILE__) . '../libs/simple_html_dom.php';
}
class RSS_News_Importer_Image_Scraper
{
    protected $logger;
    protected $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0',
    ];

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function set_post_thumbnail($post_id, $item)
    {
        // 尝试获取图片的优先顺序：
        // 1. RSS项目中的缩略图
        // 2. media:content 标签中的图片
        // 3. 从内容中获取第一张图片

        // 1. 检查RSS项目中的缩略图
        if (isset($item['thumbnail'])) {
            $thumbnail_url = strtok($item['thumbnail'], '?');
            if ($this->try_set_featured_image($post_id, $thumbnail_url, $item['title'], "Using thumbnail from RSS feed")) {
                return true;
            }
        }

        // 2. 检查media:content标签中的图片
        if (isset($item['media:content']['@attributes']['url'])) {
            $media_content_url = strtok($item['media:content']['@attributes']['url'], '?');
            if ($this->try_set_featured_image($post_id, $media_content_url, $item['title'], "Using image from media:content tag")) {
                return true;
            }
        }

        // 3. 从内容中获取第一张图片
        if (isset($item['link'])) {
            $first_image_url = $this->get_first_image_from_content($item['link']);
            if ($first_image_url) {
                if ($this->try_set_featured_image($post_id, $first_image_url, $item['title'], "Using the first image from website")) {
                    return true;
                }
            }
        }
        return false;
    }

    private function try_set_featured_image($post_id, $image_url, $title, $log_message)
    {
        $thumbnail_id = $this->set_featured_image($post_id, $image_url, $title);
        if ($thumbnail_id) {
            $this->logger->log($log_message, "info");
            return true;
        }
        return false;
    }

    private function set_featured_image($post_id, $image_url, $title)
    {
        $image_data = $this->fetch_image_data($image_url);
        if ($image_data) {
            $filename = $post_id . '-' . basename($image_url);
            $upload = wp_upload_bits($filename, null, $image_data);
            if (!$upload['error']) {
                $wp_filetype = wp_check_filetype($upload['file'], null);
                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => sanitize_file_name($title),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                $attachment_id = wp_insert_attachment($attachment, $upload['file']);
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                wp_update_attachment_metadata($attachment_id, $attach_data);
                set_post_thumbnail($post_id, $attachment_id);
                return $attachment_id;
            }
        }
        return false;
    }

    private function fetch_image_data($image_url, $retries = 3)
    {
        $attempt = 0;
        while ($attempt < $retries) {
            $response = wp_remote_get($image_url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => $this->user_agents[array_rand($this->user_agents)],
                ]
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                return wp_remote_retrieve_body($response);
            }

            $attempt++;
            if ($attempt < $retries) {
                sleep(pow(2, $attempt)); // 指数退避
            }
        }

        $this->logger->log("Failed to fetch image after $retries attempts: $image_url", 'error');
        return false;
    }

    private function get_first_image_from_content($rss_link)
    {
        // 提取基础URL
        $parsed_url = parse_url($rss_link);
        $base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
        $this->logger->log("Base URL extracted: $base_url", "debug");

        // 定义最大页数
        $max_pages = 5;

        // 循环尝试从多个页面获取图片
        for ($page = 0; $page <= $max_pages; $page++) {
            // 构建页面URL
            $url = $base_url . '/latest?page=' . $page;
            $this->logger->log("Fetching URL: $url", "debug");

            // 使用 wp_remote_get 替代 file_get_contents
            $response = wp_remote_get($url, array('timeout' => 30));

            // 如果获取失败，记录日志并继续下一个页面
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $this->logger->log("Failed to fetch content from: $url", "warning");
                continue;
            }

            $html = wp_remote_retrieve_body($response);
            $this->logger->log("Fetched content from: $url", "debug");

            // 创建DOMDocument对象并加载HTML内容
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $this->logger->log("Loaded HTML content from: $url", "debug");

            // 创建DOMXPath对象
            $xpath = new \DOMXPath($dom);

            // 根据实际的HTML结构调整XPath表达式，查找图片元素
            $images = $xpath->query('//div[contains(@class, "views-row")]//img');
            $this->logger->log("Found " . $images->length . " images on page $page", "debug");

            // 如果找到了图片元素，返回第一个图片的src属性值
            if ($images->length > 0) {
                $first_image_src = $images->item(0)->getAttribute('src');
                $this->logger->log("First image found: $first_image_src", "debug");
                return $first_image_src;
            }
        }

        // 如果在循环结束后仍未找到任何图片，记录日志并返回false
        $this->logger->log("No images found after checking $max_pages pages", "warning");
        return false;
    }

    public function fetch_multiple_images($image_urls)
    {
        $results = [];

        foreach ($image_urls as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => $this->user_agents[array_rand($this->user_agents)],
                ],
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $results[$url] = wp_remote_retrieve_body($response);
            } else {
                $this->logger->log("Failed to fetch image: " . $url, 'error');
                $results[$url] = false;
            }
        }

        return $results;
    }
}
