<p>
    <label><?php _e('Import Limit:', 'rss-news-importer'); ?></label>
    <input type="number" name="<?php echo $this->option_name; ?>[import_limit]" value="<?php echo esc_attr($import_limit); ?>" min="1" max="100">
</p>
<p class="description"><?php _e('Limit the number of posts to import per feed.', 'rss-news-importer'); ?></p>