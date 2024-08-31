<p>
    <label><?php _e('Post Status:', 'rss-news-importer'); ?></label>
    <select name="<?php echo $this->option_name; ?>[post_status]">
        <option value="draft" <?php selected($post_status, 'draft'); ?>><?php _e('Draft', 'rss-news-importer'); ?></option>
        <option value="publish" <?php selected($post_status, 'publish'); ?>><?php _e('Published', 'rss-news-importer'); ?></option>
        <option value="pending" <?php selected($post_status, 'pending'); ?>><?php _e('Pending Review', 'rss-news-importer'); ?></option>
    </select>
</p>
<p class="description"><?php _e('Choose the status for imported posts.', 'rss-news-importer'); ?></p>