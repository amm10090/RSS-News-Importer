<?php

class RSS_News_Importer_Parser {
    public function fetch_feed($url) {
        $response = wp_safe_remote_get($url);
        if (is_wp_error($response)) {
            return false;
        }
        $body = wp_remote_retrieve_body($response);
        return $this->parse_feed($body);
    }

    private function parse_feed($content) {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($content);
        if (!$feed) {
            return false;
        }

        $items = array();
        foreach ($feed->channel->item as $item) {
            $items[] = array(
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'description' => (string)$item->description,
                'pubDate' => (string)$item->pubDate,
                'author' => (string)$item->author,
                'categories' => $this->get_categories($item),
                'thumbnail' => $this->get_thumbnail_url($item),
            );
        }
        return $items;
    }

    private function get_categories($item) {
        $categories = array();
        foreach ($item->category as $category) {
            $categories[] = (string)$category;
        }
        return $categories;
    }

    private function get_thumbnail_url($item) {
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
}