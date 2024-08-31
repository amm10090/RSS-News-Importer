<?php

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

class RSS_News_Importer_Parser
{
    private $logger;

    public function __construct()
    {
        $this->logger = new RSS_News_Importer_Logger();
    }

    // 获取并解析RSS源
    public function fetch_feed($url)
    {
        $this->logger->log("Fetching feed: $url", 'info');

        $headers = array(
            'User-Agent' => 'RSS News Importer/1.0 (WordPress Plugin; ' . get_bloginfo('url') . ')',
        );

        $last_modified = get_option('rss_news_importer_last_modified_' . md5($url));
        $etag = get_option('rss_news_importer_etag_' . md5($url));

        if ($last_modified) {
            $headers['If-Modified-Since'] = $last_modified;
        }
        if ($etag) {
            $headers['If-None-Match'] = $etag;
        }

        $response = wp_safe_remote_get($url, array(
            'timeout' => 60,
            'headers' => $headers,
        ));

        if (is_wp_error($response)) {
            $this->logger->log("Error fetching feed: " . $response->get_error_message(), 'error');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 304) {
            $this->logger->log("Feed not modified since last fetch", 'info');
            return 'not_modified';
        }

        $body = wp_remote_retrieve_body($response);
        $new_last_modified = wp_remote_retrieve_header($response, 'last-modified');
        $new_etag = wp_remote_retrieve_header($response, 'etag');

        if ($new_last_modified) {
            update_option('rss_news_importer_last_modified_' . md5($url), $new_last_modified);
        }
        if ($new_etag) {
            update_option('rss_news_importer_etag_' . md5($url), $new_etag);
        }

        $parsed_feed = $this->parse_feed($body);
        if ($parsed_feed === false) {
            $this->logger->log("Unable to parse feed content", 'error');
            return false;
        }

        return $parsed_feed;
    }

    // 解析RSS源内容
    private function parse_feed($content)
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($content);
        if (!$feed) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $error_messages = array();
            foreach ($errors as $error) {
                $error_messages[] = $error->message;
            }
            $this->logger->log("XML parsing failed: " . implode(', ', $error_messages), 'error');
            return false;
        }

        $items = array();
        $feed_items = $feed->channel->item ?? $feed->item ?? array();

        foreach ($feed_items as $item) {
            $parsed_item = array(
                'title' => (string)($item->title ?? ''),
                'link' => (string)($item->link ?? ''),
                'guid' => (string)($item->guid ?? ''),
                'description' => (string)($item->description ?? ''),
                'pubDate' => (string)($item->pubDate ?? ''),
                'author' => (string)($item->author ?? $item->children('dc', true)->creator ?? ''),
                'categories' => $this->get_categories($item),
                'thumbnail' => $this->get_thumbnail_url($item),
                'content' => (string)($item->children('content', true)->encoded ?? ''),
            );

            $items[] = $parsed_item;
        }

        if (empty($items)) {
            $this->logger->log("No items found in the feed", 'warning');
            return false;
        }

        return $items;
    }

    // 获取RSS项目的分类
    private function get_categories($item)
    {
        $categories = array();
        foreach ($item->category as $category) {
            $categories[] = (string)$category;
        }
        return $categories;
    }

    // 获取RSS项目的缩略图URL
    private function get_thumbnail_url($item)
    {
        $namespaces = $item->getNamespaces(true);
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->content)) {
                $attributes = $media->content->attributes();
                if (isset($attributes['url'])) {
                    return (string)$attributes['url'];
                }
            }
            if (isset($media->thumbnail)) {
                $attributes = $media->thumbnail->attributes();
                if (isset($attributes['url'])) {
                    return (string)$attributes['url'];
                }
            }
        }
        return '';
    }

    // 预览RSS源
    public function preview_feed($url, $limit = 5)
    {
        $feed_data = $this->fetch_feed($url);

        if ($feed_data === false) {
            return new WP_Error('fetch_error', 'Failed to fetch or parse the feed.');
        }

        if ($feed_data === 'not_modified') {
            return new WP_Error('not_modified', 'Feed has not been modified since last fetch.');
        }

        $preview_items = array_slice($feed_data, 0, $limit);

        $preview_html = '<ul class="feed-preview-list">';
        foreach ($preview_items as $item) {
            $preview_html .= '<li class="feed-preview-item">';
            $preview_html .= '<h3>' . esc_html($item['title']) . '</h3>';
            $preview_html .= '<p>' . wp_trim_words(wp_strip_all_tags($item['description']), 30, '...') . '</p>';
            if (!empty($item['thumbnail'])) {
                $preview_html .= '<img src="' . esc_url($item['thumbnail']) . '" alt="Thumbnail" class="feed-preview-thumbnail">';
            }
            $preview_html .= '<a href="' . esc_url($item['link']) . '" target="_blank" class="feed-preview-link">Read More</a>';
            $preview_html .= '</li>';
        }
        $preview_html .= '</ul>';

        return $preview_html;
    }
}