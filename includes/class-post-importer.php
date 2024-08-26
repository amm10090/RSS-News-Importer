<?php

class RSS_News_Importer_Post_Importer {
    private $parser;
    private $logger;
    private $option_name = 'rss_news_importer_options';

    public function __construct() {
        $this->parser = new RSS_News_Importer_Parser();
        $this->logger = new RSS_News_Importer_Logger();
    }

    public function import_feed($url) {
        $options = get_option($this->option_name);
        $import_limit = isset($options['import_limit']) ? intval($options['import_limit']) : 10;

        $rss_items = $this->parser->fetch_feed($url);
        if (!$rss_items) {
            $this->logger->log("Failed to fetch or parse feed: $url", 'error');
            return false;
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

        $this->logger->log("Imported $imported_count items from $url (Skipped $skipped_count duplicates)", 'info');
        return $imported_count;
    }

    private function import_item($item) {
        $guid = $item['guid'] ?: $item['link'];
        if ($this->post_exists($guid)) {
            $this->logger->log("Skipped duplicate item: " . $item['title'], 'info');
            return 'skipped';
        }

        $post_content = $this->filter_content($item['content'] ?: $item['description']);
        $first_image = $this->get_first_image_from_content($post_content);

        $post_data = array(
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => $post_content,
            'post_excerpt'  => wp_trim_words($item['description'], 55, '...'),
            'post_date'     => date('Y-m-d H:i:s', strtotime($item['pubDate'])),
            'post_status'   => 'draft',
            'post_author'   => $this->get_default_author(),
            'post_type'     => 'post',
            'post_category' => $this->get_default_category(),
            'meta_input'    => array(
                'rss_news_importer_guid' => $guid,
                'rss_news_importer_link' => $item['link'],
                'rss_news_importer_author' => $item['author'],
            ),
        );

        if ($first_image) {
            $post_data['meta_input']['rss_news_importer_cover_image'] = $first_image;
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->logger->log("Failed to import item: " . $item['title'], 'error');
            return false;
        }

        $this->set_featured_image($post_id, $item['thumbnail']);

        return true;
    }

    private function post_exists($guid) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rss_news_importer_guid' AND meta_value=%s", $guid));
    }

    private function get_default_author() {
        $options = get_option($this->option_name);
        return isset($options['import_author']) ? intval($options['import_author']) : 1;
    }

    private function get_default_category() {
        $options = get_option($this->option_name);
        $category_id = isset($options['import_category']) ? intval($options['import_category']) : 1;
        return array($category_id);
    }

    private function set_featured_image($post_id, $image_url) {
        if (!$image_url) {
            return;
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
    }

    private function get_first_image_from_content($content) {
        preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $image);
        return isset($image['src']) ? $image['src'] : false;
    }

    private function filter_content($content) {
        $options = get_option($this->option_name);
        $exclusions = isset($options['content_exclusions']) ? $options['content_exclusions'] : '';
        
        if (!empty($exclusions)) {
            $exclusions = explode("\n", $exclusions);
            foreach ($exclusions as $exclusion) {
                $exclusion = trim($exclusion);
                if (strpos($exclusion, '#') === 0 || strpos($exclusion, '.') === 0) {
                    // CSS selector
                    $content = preg_replace('/<[^>]*' . preg_quote($exclusion, '/') . '[^>]*>.*?<\/[^>]*>/s', '', $content);
                } else {
                    // Text pattern
                    $content = str_replace($exclusion, '', $content);
                }
            }
        }
        
        return $content;
    }
}