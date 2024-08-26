<?php

class RSS_News_Importer_Post_Importer {
    private $parser;
    private $logger;

    public function __construct() {
        $this->parser = new RSS_News_Importer_Parser();
        $this->logger = new RSS_News_Importer_Logger();
    }

    public function import_feed($url) {
        $items = $this->parser->fetch_feed($url);
        if (!$items) {
            $this->logger->log("Failed to fetch feed: $url", 'error');
            return false;
        }
        $imported_count = 0;
        foreach ($items as $item) {
            if ($this->import_item($item)) {
                $imported_count++;
            }
        }
        $this->logger->log("Imported $imported_count items from $url", 'info');
        return $imported_count;
    }

    private function import_item($item) {
        if ($this->post_exists($item['link'])) {
            return false;
        }

        $post_data = array(
            'post_title'    => wp_strip_all_tags($item['title']),
            'post_content'  => wp_kses_post($item['description']),
            'post_date'     => date('Y-m-d H:i:s', strtotime($item['pubDate'])),
            'post_status'   => 'draft',
            'post_author'   => 1,
            'post_type'     => 'post',
            'post_category' => $this->get_category_ids($item['categories']),
            'meta_input'    => array(
                'rss_news_importer_link' => $item['link'],
                'rss_news_importer_author' => $item['author'],
            ),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->logger->log("Failed to import item: " . $item['title'], 'error');
            return false;
        }

        if (!empty($item['thumbnail'])) {
            $this->set_featured_image($post_id, $item['thumbnail']);
        }

        return true;
    }

    private function post_exists($link) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rss_news_importer_link' AND meta_value=%s", $link));
    }

    private function get_category_ids($categories) {
        $category_ids = array();
        foreach ($categories as $category_name) {
            $category = get_category_by_name($category_name);
            if (!$category) {
                $category_id = wp_create_category($category_name);
            } else {
                $category_id = $category->term_id;
            }
            $category_ids[] = $category_id;
        }
        return $category_ids;
    }

    private function set_featured_image($post_id, $image_url) {
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
}