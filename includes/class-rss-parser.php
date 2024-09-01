<?php

/**
 * Handles the parsing of RSS feeds.
 *
 * @link       https://blog.amoze.cc/
 * @since      1.0.0
 *
 * @package    RSS_News_Importer
 * @subpackage RSS_News_Importer/includes
 */

class RSS_News_Importer_Parser {

    /**
     * The logger instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      RSS_News_Importer_Logger    $logger    The logger instance.
     */
    private $logger;

    /**
     * The cache instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      RSS_News_Importer_Cache    $cache    The cache instance.
     */
    private $cache;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    RSS_News_Importer_Logger    $logger    The logger instance.
     * @param    RSS_News_Importer_Cache     $cache     The cache instance.
     */
    public function __construct($logger, $cache) {
        $this->logger = $logger;
        $this->cache = $cache;
    }
    /**
     * Fetch and parse an RSS feed.
     *
     * @since    1.0.0
     * @param    string    $url    The URL of the RSS feed.
     * @return   array|WP_Error    An array of parsed items or WP_Error on failure.
     */
    public function fetch_feed($url) {
        $this->logger->log("Fetching feed: $url", 'info');

        // Check cache first
        $cached_data = $this->cache->get_cached_feed($url);
        if ($cached_data !== false) {
            $this->logger->log("Using cached data for feed: $url", 'info');
            return $cached_data;
        }

        $headers = array(
            'User-Agent' => 'RSS News Importer/1.0 (WordPress; ' . get_bloginfo('url') . ')',
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
            $this->logger->log("Failed to fetch feed: " . $response->get_error_message(), 'error');
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 304) {
            $this->logger->log("Feed not modified: $url", 'info');
            return new WP_Error('not_modified', 'Feed content has not been modified');
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
        if (is_wp_error($parsed_feed)) {
            return $parsed_feed;
        }

        // Cache the parsed feed
        $this->cache->set_cached_feed($url, $parsed_feed);

        return $parsed_feed;
    }

    /**
     * Parse the RSS feed content.
     *
     * @since    1.0.0
     * @param    string    $content    The raw content of the RSS feed.
     * @return   array|WP_Error        An array of parsed items or WP_Error on failure.
     */
    private function parse_feed($content) {
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
            return new WP_Error('xml_parse_error', 'XML parsing failed: ' . implode(', ', $error_messages));
        }

        $items = array();
        $feed_items = $feed->channel->item ?? $feed->item ?? array();

        foreach ($feed_items as $item) {
            $parsed_item = $this->parse_item($item);
            if (!is_wp_error($parsed_item)) {
                $items[] = $parsed_item;
            }
        }

        if (empty($items)) {
            $this->logger->log("No items found in the feed", 'warning');
            return new WP_Error('no_items', 'No items found in the feed');
        }

        return $items;
    }

    /**
     * Parse a single RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item to parse.
     * @return   array|WP_Error               The parsed item or WP_Error on failure.
     */
    private function parse_item($item) {
        try {
            $parsed_item = array(
                'title' => $this->get_item_title($item),
                'link' => (string)($item->link ?? ''),
                'guid' => (string)($item->guid ?? ''),
                'description' => $this->get_item_description($item),
                'content' => $this->get_item_content($item),
                'pubDate' => $this->get_item_pub_date($item),
                'author' => $this->get_item_author($item),
                'categories' => $this->get_item_categories($item),
                'thumbnail' => $this->get_item_thumbnail($item),
            );

            // Remove empty values
            $parsed_item = array_filter($parsed_item);

            return $parsed_item;
        } catch (Exception $e) {
            $this->logger->log("Failed to parse item: " . $e->getMessage(), 'error');
            return new WP_Error('parse_item_error', $e->getMessage());
        }
    }

    /**
     * Get the title of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   string                       The item title.
     */
    private function get_item_title($item) {
        return html_entity_decode(trim((string)($item->title ?? '')), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the description of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   string                       The item description.
     */
    private function get_item_description($item) {
        return html_entity_decode(trim((string)($item->description ?? '')), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the content of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   string                       The item content.
     */
    private function get_item_content($item) {
        $content = $item->children('content', true)->encoded ?? '';
        return html_entity_decode(trim((string)$content), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the publication date of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   string                       The item publication date.
     */
    private function get_item_pub_date($item) {
        $pubDate = (string)($item->pubDate ?? '');
        return $pubDate ? date('Y-m-d H:i:s', strtotime($pubDate)) : '';
    }

    /**
     * Get the author of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   string                       The item author.
     */
    private function get_item_author($item) {
        return (string)($item->author ?? $item->children('dc', true)->creator ?? '');
    }

    /**
     * Get the categories of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   array                        The item categories.
     */
    private function get_item_categories($item) {
        $categories = array();
        foreach ($item->category as $category) {
            $categories[] = (string)$category;
        }
        return $categories;
    }

    /**
     * Get the thumbnail URL of an RSS item.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item.
     * @return   string                       The item thumbnail URL.
     */
    private function get_item_thumbnail($item) {
        $namespaces = $item->getNamespaces(true);
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->content)) {
                $attributes = $media->content->attributes();
                if (isset($attributes['url'])) {
                    return (string)$attributes['url'];
                }
            }
        }
        return '';
    }

    /**
     * Preview an RSS feed.
     *
     * @since    1.0.0
     * @param    string    $url      The URL of the RSS feed.
     * @param    int       $limit    Optional. The number of items to preview. Default 5.
     * @return   string|WP_Error     HTML preview of the feed or WP_Error on failure.
     */
    public function preview_feed($url, $limit = 5) {
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
     * Get HTML for a preview item.
     *
     * @since    1.0.0
     * @param    array     $item    The parsed RSS item.
     * @return   string             HTML for the preview item.
     */
    private function get_preview_item_html($item) {
        $html = '<li class="feed-preview-item">';
        $html .= '<h3>' . esc_html($item['title']) . '</h3>';
        $html .= '<p>' . wp_trim_words(wp_strip_all_tags($item['description']), 30, '...') . '</p>';
        if (!empty($item['thumbnail'])) {
            $html .= '<img src="' . esc_url($item['thumbnail']) . '" alt="Thumbnail" class="feed-preview-thumbnail">';
        }
        $html .= '<a href="' . esc_url($item['link']) . '" target="_blank" class="feed-preview-link">Read More</a>';
        $html .= '</li>';
        return $html;
    }
}