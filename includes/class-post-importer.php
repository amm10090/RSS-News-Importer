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

        $rss = fetch_feed($url);
        if (is_wp_error($rss)) {
            $this->logger->log("Failed to fetch feed: $url", 'error');
            return false;
        }

        $maxitems = $rss->get_item_quantity($import_limit);
        $rss_items = $rss->get_items(0, $maxitems);

        $imported_count = 0;
        foreach ($rss_items as $item) {
            if ($this->import_item($item)) {
                $imported_count++;
            }
        }

        $this->logger->log("Imported $imported_count items from $url", 'info');
        return $imported_count;
    }

    private function import_item($item) {
        $link = $item->get_permalink();
        if ($this->post_exists($link)) {
            return false;
        }

        $post_data = array(
            'post_title'    => wp_strip_all_tags($item->get_title()),
            'post_content'  => $item->get_content(),
            'post_excerpt'  => $item->get_description(),
            'post_date'     => $item->get_date('Y-m-d H:i:s'),
            'post_status'   => 'draft',
            'post_author'   => $this->get_default_author(),
            'post_type'     => 'post',
            'post_category' => $this->get_default_category(),
            'meta_input'    => array(
                'rss_news_importer_link' => $link,
                'rss_news_importer_author' => $item->get_author(),
            ),
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            $this->logger->log("Failed to import item: " . $item->get_title(), 'error');
            return false;
        }

        $this->set_featured_image($post_id, $item);

        return true;
    }

    private function post_exists($link) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rss_news_importer_link' AND meta_value=%s", $link));
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

    private function set_featured_image($post_id, $item) {
        $image_url = $this->get_image_from_item($item);
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

    private function get_image_from_item($item) {
        $enclosures = $item->get_enclosures();
        foreach ($enclosures as $enclosure) {
            if ($enclosure->get_type() === 'image/jpeg' || $enclosure->get_type() === 'image/png') {
                return $enclosure->get_link();
            }
        }

        // 如果没有找到封面图，可以尝试从内容中提取第一张图片
        $content = $item->get_content();
        preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $image);
        return isset($image['src']) ? $image['src'] : false;
    }
}