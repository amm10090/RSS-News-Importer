<p>
    <label for="content-exclusions"><?php _e('Content Exclusions:', 'rss-news-importer'); ?></label>
    <textarea id="content-exclusions" name="<?php echo $this->option_name; ?>[content_exclusions]" rows="4" cols="50"><?php echo esc_textarea($exclusions); ?></textarea>
</p>
<p class="description"><?php _e('Enter CSS selectors or text patterns to exclude, one per line.', 'rss-news-importer'); ?></p>