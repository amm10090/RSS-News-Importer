<?php

class RSS_News_Importer_Post_Importer {
    private $parser;

    public function __construct() {
        $this->parser = new RSS_News_Importer_Parser();
    }

    public function import_feed($url) {
        $items = $this->parser->fetch_feed($url);
        if (!$items) {
            return false;
        }
        foreach ($items as $item) {
            $this->import_item($item);
        }
        return true;
    }

    private function import_item($item) {
        if ($this->post_exists($item['link'])) {
            return;
        }
        $post_id = wp_insert_post(array(
            'post_title' => $item['title'],
            'post_content' => $item['description'],
            'post_date' => date('Y-m-d H:i:s', strtotime($item['pubDate'])),
            'post_status' => 'publish',
            'post_type' => 'post',
        ));
        if ($post_id) {
            add_post_meta($post_id, 'rss_news_importer_link', $item['link']);
            if (!empty($item['thumbnail'])) {
                $this->set_featured_image($post_id, $item['thumbnail']);
            }
        }
    }

    private function post_exists($link) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rss_news_importer_link' AND meta_value=%s", $link));
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