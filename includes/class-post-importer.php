<?php

class RSS_News_Importer_Post_Importer
{
    private $parser;
    private $logger;
    private $cache;
    private $dashboard;
    private $option_name = 'rss_news_importer_options';
    private $plugin_name;
    private $version;
    private $image_scraper;

    /**
     * 构造函数：初始化导入器
     */
    public function __construct()
    {
        $this->parser = new RSS_News_Importer_Parser();
        $this->logger = new RSS_News_Importer_Logger();
        $this->cache = new RSS_News_Importer_Cache($this->plugin_name, $this->version);
        $this->dashboard = new RSS_News_Importer_Dashboard($this->plugin_name, $this->version);
    }

    /**
     * 设置图片抓取器
     */
    public function set_image_scraper($scraper)
    {
        $this->image_scraper = $scraper;
    }

    /**
     * 导入RSS源
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

        return $imported_count;
    }

    /**
     * 导入单个项目
     */
    private function import_item($item)
    {
        $guid = $item['guid'] ?: $item['link'];
        if ($this->post_exists($guid)) {
            $this->logger->log("Skipped duplicate item: " . $item['title'], 'info');
            return 'skipped';
        }

        $post_content = $this->filter_content($item['content'] ?: $item['description']);
        $options = get_option('rss_news_importer_options');
        $post_status = isset($options['post_status']) ? $options['post_status'] : 'draft';
        $post_data = array(
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => $post_content,
            'post_excerpt'  => wp_trim_words($item['description'], 55, '...'),
            'post_date'     => date('Y-m-d H:i:s', strtotime($item['pubDate'])),
            'post_status'   => 'post_status',
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

        // 设置特色图片
        $this->logger->log("Attempting to set featured image for: " . $item['title'], 'debug');

        $thumbnail_id = $this->set_featured_image($post_id, $item['thumbnail'], $item['title']);
        if ($thumbnail_id) {
            $this->logger->log("Featured image set from RSS thumbnail", 'debug');
        } else {
            $this->logger->log("Failed to set featured image from RSS thumbnail", 'debug');

            $first_image = $this->get_first_image_from_content($post_content);
            if ($first_image) {
                $thumbnail_id = $this->set_featured_image($post_id, $first_image, $item['title']);
                if ($thumbnail_id) {
                    $this->logger->log("Featured image set from content", 'debug');
                } else {
                    $this->logger->log("Failed to set featured image from content", 'debug');
                }
            } else {
                $this->logger->log("No image found in content", 'debug');
            }
        }

        if (!$thumbnail_id) {
            $this->logger->log("Attempting to scrape image from latest pages", 'debug');
            $base_url = $this->get_website_url();
            $scraped_image_url = $this->scrape_image_from_latest_pages($item['title'], $item['link'], $item['pubDate'], $base_url);
            if ($scraped_image_url) {
                $thumbnail_id = $this->set_featured_image($post_id, $scraped_image_url, $item['title']);
                if ($thumbnail_id) {
                    $this->logger->log("Featured image set from scraped image: " . $scraped_image_url, 'debug');
                } else {
                    $this->logger->log("Failed to set featured image from scraped image: " . $scraped_image_url, 'debug');
                }
            } else {
                $this->logger->log("No image found from scraping latest pages", 'debug');
            }
        }

        if ($thumbnail_id) {
            update_post_meta($post_id, 'rss_news_importer_cover_image', wp_get_attachment_url($thumbnail_id));
            $this->logger->log("Cover image meta updated for post: " . $post_id, 'debug');
        } else {
            $this->logger->log("No cover image set for post: " . $post_id, 'debug');
        }

        return true;
    }

    /**
     * 检查文章是否已存在
     */
    private function post_exists($guid)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rss_news_importer_guid' AND meta_value=%s", $guid));
    }

    /**
     * 获取默认作者
     */
    private function get_default_author()
    {
        $options = get_option($this->option_name);
        return isset($options['import_author']) ? intval($options['import_author']) : 1;
    }

    /**
     * 获取默认分类
     */
    private function get_default_category()
    {
        $options = get_option($this->option_name);
        $category_id = isset($options['import_category']) ? intval($options['import_category']) : 1;
        return array($category_id);
    }

    /**
     * 获取网站URL
     */
    private function get_website_url()
    {
        $options = get_option($this->option_name);
        return isset($options['website_url']) ? $options['website_url'] : 'https://newsbusters.org';
    }
    //
    /**
     * 设置特色图片
     */
    private function set_featured_image($post_id, $image_url, $title)
    {
        if (!$image_url) {
            return false;
        }

        $clean_image_url = preg_replace('/\?.*/', '', $image_url);

        $cached_image_id = $this->get_cached_image($clean_image_url);
        if ($cached_image_id) {
            set_post_thumbnail($post_id, $cached_image_id);
            return $cached_image_id;
        }

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        add_filter('http_request_args', function ($args, $url) use ($image_url) {
            $args['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
            $args['headers']['Referer'] = $image_url;
            return $args;
        }, 10, 2);

        $tmp = $this->download_with_exponential_backoff($clean_image_url);

        if (is_wp_error($tmp)) {
            $this->logger->log("Failed to download image: " . $clean_image_url . ". Error: " . $tmp->get_error_message(), 'error');
            return false;
        }

        $compressed_tmp = $this->compress_image($tmp);
        if ($compressed_tmp) {
            @unlink($tmp);
            $tmp = $compressed_tmp;
        }

        $file_array = array(
            'name' => sanitize_file_name(basename($clean_image_url)),
            'tmp_name' => $tmp
        );

        $filetype = wp_check_filetype_and_ext($tmp, $file_array['name']);
        if (!$filetype['type']) {
            $mime = mime_content_type($tmp);
            if (strpos($mime, 'image/') === 0) {
                $ext = str_replace('image/', '', $mime);
                $filetype['type'] = $mime;
                $file_array['name'] .= '.' . $ext;
            } else {
                $this->logger->log("Invalid file type: " . $clean_image_url, 'error');
                @unlink($tmp);
                return false;
            }
        }

        $thumbnail_id = media_handle_sideload($file_array, $post_id, $title);

        @unlink($tmp);

        if (is_wp_error($thumbnail_id)) {
            $this->logger->log("Failed to add image to media library: " . $clean_image_url . ". Error: " . $thumbnail_id->get_error_message(), 'error');
            return false;
        }

        set_post_thumbnail($post_id, $thumbnail_id);

        $this->cache_image($clean_image_url, $thumbnail_id);

        $this->save_image_metadata($thumbnail_id);

        return $thumbnail_id;
    }

    /**
     * 从内容中获取第一张图片
     */
    private function get_first_image_from_content($content)
    {
        preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $image);
        return isset($image['src']) ? $image['src'] : false;
    }

    /**
     * 使用指数退避策略下载
     */
    private function download_with_exponential_backoff($url, $max_attempts = 5)
    {
        $attempt = 0;
        do {
            $tmp = download_url($url);
            if (!is_wp_error($tmp)) {
                return $tmp;
            }
            $attempt++;
            if ($attempt < $max_attempts) {
                sleep(pow(2, $attempt));
            }
        } while ($attempt < $max_attempts);

        return $tmp;
    }

    /**
     * 压缩图片
     */
    private function compress_image($file_path)
    {
        $image = wp_get_image_editor($file_path);
        if (!is_wp_error($image)) {
            $image->resize(1200, 1200, false);
            $image->set_quality(85);
            $compressed_file = $file_path . '_compressed';
            $image->save($compressed_file);
            return $compressed_file;
        }
        return false;
    }

    /**
     * 获取缓存的图片
     */
    private function get_cached_image($url)
    {
        return get_transient('rss_importer_image_' . md5($url));
    }

    /**
     * 缓存图片
     */
    private function cache_image($url, $image_id)
    {
        set_transient('rss_importer_image_' . md5($url), $image_id, DAY_IN_SECONDS);
    }

    /**
     * 保存图片元数据
     */
    private function save_image_metadata($attachment_id)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['image_meta'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_exif', $metadata['image_meta']);
        }
    }

    /**
     * 过滤内容
     */
    private function filter_content($content)
    {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';
        $convert_links = isset($options['convert_links']) ? $options['convert_links'] : '';
        $keyword_filters = isset($options['keyword_filters']) ? $options['keyword_filters'] : '';

        if (empty($exclusions) && empty($convert_links) && empty($keyword_filters) && !has_filter('rss_news_importer_filter_content')) {
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

        if (!empty($keyword_filters)) {
            $keyword_filters = explode("\n", $keyword_filters);
            foreach ($keyword_filters as $keyword) {
                $keyword = trim($keyword);
                if (empty($keyword)) continue;
                $paragraphs = $xpath->query("//p[contains(text(), '$keyword')]");
                foreach ($paragraphs as $paragraph) {
                    $paragraph->parentNode->removeChild($paragraph);
                }
            }
        }

        $content = $this->sanitize_html($dom->saveHTML());

        $content = apply_filters('rss_news_importer_filter_content', $content);

        return $content;
    }

    /**
     * 净化HTML内容
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
     * 从最新页面抓取图片
     */
    /**
     * 从最新页面抓取图片
     */
    private function scrape_image_from_latest_pages($post_title, $post_link, $post_date, $base_url)
    {
        $this->logger->log("Scraping image for post: " . $post_title, 'debug');
        $this->logger->log("Post link: " . $post_link, 'debug');
        $this->logger->log("Post date: " . $post_date, 'debug');

        for ($page = 0; $page <= 5; $page++) {
            $url = $base_url . "/latest?page=" . $page;
            $this->logger->log("Checking page: " . $url, 'debug');

            $html = $this->fetch_page_content($url);

            if (!$html) {
                $this->logger->log("Failed to fetch content from: " . $url, 'debug');
                continue;
            }

            $this->logger->log("Successfully fetched content from: " . $url, 'debug');

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // 更新选择器以匹配 NewsBusters 的结构
            $posts = $xpath->query("//div[contains(@class, 'views-row')]");
            $this->logger->log("Found " . $posts->length . " posts on page", 'debug');

            foreach ($posts as $post) {
                // 更新选择器以匹配 NewsBusters 的结构
                $title_element = $xpath->query(".//h3[contains(@class, 'views-field-field-short-title')]//div[@class='field-content']", $post)->item(0);
                $link_element = $xpath->query(".//a[@class='card-links']", $post)->item(0);
                $date_element = $xpath->query(".//div[contains(@class, 'views-field-created-1')]//span[@class='field-content']", $post)->item(0);
                $image_element = $xpath->query(".//div[contains(@class, 'views-field-field-images')]//img", $post)->item(0);

                if ($title_element && $link_element && $date_element) {
                    $title = $title_element->textContent;
                    $link = $base_url . $link_element->getAttribute('href');
                    $date = $date_element->textContent;

                    $this->logger->log("Comparing post: " . $title, 'debug');
                    $this->logger->log("Post link: " . $link, 'debug');
                    $this->logger->log("Post date: " . $date, 'debug');

                    if ($this->is_matching_post($title, $post_title, $link, $post_link, $date, $post_date)) {
                        $this->logger->log("Found matching post: " . $title, 'debug');

                        if ($image_element) {
                            $image_url = $image_element->getAttribute('data-src') ?: $image_element->getAttribute('src');
                            $this->logger->log("Found image: " . $image_url, 'debug');
                            return $image_url;
                        } else {
                            $this->logger->log("No image found for matching post", 'debug');
                        }
                    } else {
                        $this->logger->log("Post does not match", 'debug');
                    }
                } else {
                    $this->logger->log("Missing essential elements for post comparison", 'debug');
                }
            }
        }

        $this->logger->log("No matching post found after checking all pages", 'debug');
        return false;
    }
    /**
     * 获取页面内容
     */
    private function fetch_page_content($url)
    {
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return false;
        }
        return wp_remote_retrieve_body($response);
    }


    /**
     * 判断是否为匹配的文章
     */
    private function is_matching_post($scraped_title, $post_title, $scraped_link, $post_link, $scraped_date, $post_date)
    {
        // 标题相似度检查
        $title_similarity = $this->calculate_similarity($scraped_title, $post_title);
        $this->logger->log("Title similarity: $title_similarity", 'debug');
        $this->logger->log("Scraped title: $scraped_title", 'debug');
        $this->logger->log("Post title: $post_title", 'debug');
        if ($title_similarity < 0.8) {  // 80% 相似度阈值
            $this->logger->log("Title similarity below threshold", 'debug');
            return false;
        }

        // URL 匹配检查
        $scraped_path = parse_url($scraped_link, PHP_URL_PATH);
        $post_path = parse_url($post_link, PHP_URL_PATH);
        $this->logger->log("Comparing URLs: $scraped_path vs $post_path", 'debug');
        if ($scraped_path !== $post_path) {
            $this->logger->log("URLs do not match", 'debug');
            return false;
        }

        // 日期匹配检查（如果可用）
        if ($scraped_date && $post_date) {
            $scraped_timestamp = strtotime($scraped_date);
            $post_timestamp = strtotime($post_date);
            $time_difference = abs($scraped_timestamp - $post_timestamp);
            $this->logger->log("Scraped date: $scraped_date", 'debug');
            $this->logger->log("Post date: $post_date", 'debug');
            $this->logger->log("Time difference: $time_difference seconds", 'debug');
            if ($time_difference > 86400) {  // 允许1天的误差
                $this->logger->log("Date difference exceeds threshold", 'debug');
                return false;
            }
        } else {
            $this->logger->log("Date comparison skipped due to missing data", 'debug');
        }

        $this->logger->log("Post match found", 'debug');
        return true;
    }
    /**
     * 计算字符串相似度
     */
    private function calculate_similarity($str1, $str2)
    {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        $distance = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        return 1 - ($distance / $maxLength);
    }
}
