<?php

class RSS_News_Importer_Parser {
    public function fetch_feed($url) {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 60,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36',
        ));

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