<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RSS 新闻导入器解析器类
 */
class RSS_News_Importer_Parser
{
    /**
     * 日志记录器实例
     * @var RSS_News_Importer_Logger
     */
    private $logger;

    /**
     * 缓存实例
     * @var RSS_News_Importer_Cache
     */
    private $cache;

    /**
     * 用户代理列表
     * @var array
     */
    private $user_agents = array(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0',
    );

    /**
     * 构造函数
     * 
     * @param RSS_News_Importer_Logger $logger 日志记录器实例
     * @param RSS_News_Importer_Cache $cache 缓存实例
     */
    public function __construct($logger, $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * 获取并解析RSS源
     * 
     * @param string $url RSS源URL
     * @param bool $force_refresh 是否强制刷新
     * @return array|WP_Error 解析后的数据或错误对象
     */
    public function fetch_feed($url, $force_refresh = false)
    {
        $this->logger->log("正在获取源: " . $url, 'info');

        $cache_key = 'rss_feed_' . md5($url);

        if (!$force_refresh) {
            $cached_data = $this->cache->get($cache_key);
            if ($cached_data !== false) {
                $this->logger->log("使用缓存数据: " . $url, 'info');
                return $cached_data;
            }
        }

        $response = $this->fetch_remote_feed($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $parsed_feed = $this->parse_feed($response['body']);

        if (!is_wp_error($parsed_feed)) {
            $this->cache->set($cache_key, $parsed_feed, 3600); // 缓存1小时
        }

        return $parsed_feed;
    }

    /**
     * 获取远程RSS源
     * 
     * @param string $url RSS源URL
     * @return array|WP_Error 响应数据或错误对象
     */
    private function fetch_remote_feed($url)
    {
        $args = array(
            'timeout'     => 60,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => $this->user_agents[array_rand($this->user_agents)],
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $this->logger->log("获取源失败: " . $response->get_error_message(), 'error');
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->logger->log("HTTP错误: " . $response_code, 'error');
            return new WP_Error('http_error', "HTTP错误: " . $response_code);
        }

        return $response;
    }

    /**
     * 解析源内容
     * 
     * @param string $content 源内容
     * @return array|WP_Error 解析后的数据或错误对象
     */
    private function parse_feed($content)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            $this->logger->log("XML解析失败", 'error');
            return new WP_Error('xml_parse_error', '无效的XML格式');
        }

        $items = array();

        if ($xml->channel->item) { // RSS
            foreach ($xml->channel->item as $item) {
                $items[] = $this->parse_rss_item($item);
            }
        } elseif ($xml->entry) { // Atom
            foreach ($xml->entry as $item) {
                $items[] = $this->parse_atom_item($item);
            }
        } else {
            $this->logger->log("未知的Feed格式", 'error');
            return new WP_Error('unknown_feed_format', '未知的Feed格式');
        }

        return $items;
    }

    /**
     * 解析RSS项目
     * 
     * @param SimpleXMLElement $item RSS项目
     * @return array 解析后的项目数据
     */
    private function parse_rss_item($item)
    {
        return array(
            'title'       => (string)$item->title,
            'link'        => (string)$item->link,
            'description' => (string)$item->description,
            'pubDate'     => $this->parse_date((string)$item->pubDate),
            'guid'        => (string)$item->guid,
            'author'      => (string)$item->author,
            'thumbnail'   => $this->extract_thumbnail($item),
        );
    }

    /**
     * 解析Atom项目
     * 
     * @param SimpleXMLElement $item Atom项目
     * @return array 解析后的项目数据
     */
    private function parse_atom_item($item)
    {
        return array(
            'title'       => (string)$item->title,
            'link'        => (string)$item->link['href'],
            'description' => (string)$item->summary,
            'pubDate'     => $this->parse_date((string)$item->published),
            'guid'        => (string)$item->id,
            'author'      => (string)$item->author->name,
            'thumbnail'   => $this->extract_thumbnail($item),
        );
    }

    /**
     * 解析日期
     * 
     * @param string $date_string 日期字符串
     * @return string 格式化的日期
     */
    private function parse_date($date_string)
    {
        $date = date_create($date_string);
        return $date ? date_format($date, 'Y-m-d H:i:s') : '';
    }

    /**
     * 从项目中提取缩略图
     * 
     * @param SimpleXMLElement $item 项目数据
     * @return string 缩略图URL
     */
    private function extract_thumbnail($item)
    {
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->thumbnail)) {
                return (string)$media->thumbnail->attributes()->url;
            }
            if (isset($media->content)) {
                return (string)$media->content->attributes()->url;
            }
        }

        // 从内容中提取第一张图片
        $content = (string)$item->description ?? (string)$item->content;
        preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $matches);
        if (isset($matches['src'])) {
            return $matches['src'];
        }

        return '';
    }

    /**
     * 验证RSS源
     * 
     * @param string $url RSS源URL
     * @return bool|WP_Error 验证结果
     */
    public function validate_feed($url)
    {
        $response = $this->fetch_remote_feed($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (
            strpos($content_type, 'application/rss+xml') === false &&
            strpos($content_type, 'application/atom+xml') === false &&
            strpos($content_type, 'text/xml') === false
        ) {
            return new WP_Error('invalid_feed', '无效的内容类型: ' . $content_type);
        }

        $parsed_feed = $this->parse_feed(wp_remote_retrieve_body($response));
        if (is_wp_error($parsed_feed)) {
            return $parsed_feed;
        }

        return true;
    }

    /**
     * 预览RSS源
     * 
     * @param string $url RSS源URL
     * @param int $limit 预览项目数量
     * @return string|WP_Error 预览HTML或错误对象
     */
    public function preview_feed($url, $limit = 5)
    {
        $feed_data = $this->fetch_feed($url);

        if (is_wp_error($feed_data)) {
            return $feed_data;
        }

        $preview_items = array_slice($feed_data, 0, $limit);

        $preview_html = '<ul class="feed-preview-list">';
        foreach ($preview_items as $item) {
            $preview_html .= $this->get_preview_item_html($item);
        }
        $preview_html .= '</ul>';

        return $preview_html;
    }

    /**
     * 获取预览项目的HTML
     * 
     * @param array $item 项目数据
     * @return string 项目HTML
     */
    private function get_preview_item_html($item)
    {
        $html = '<li class="feed-preview-item">';
        $html .= '<h3>' . esc_html($item['title']) . '</h3>';
        $html .= '<p>' . wp_trim_words(wp_strip_all_tags($item['description']), 30, '...') . '</p>';
        if (!empty($item['thumbnail'])) {
            $html .= '<img src="' . esc_url($item['thumbnail']) . '" alt="缩略图" class="feed-preview-thumbnail">';
        }
        $html .= '<a href="' . esc_url($item['link']) . '" target="_blank" class="feed-preview-link">阅读更多</a>';
        $html .= '</li>';
        return $html;
    }
}
