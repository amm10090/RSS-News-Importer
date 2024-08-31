<select name="<?php echo $this->option_name; ?>[update_frequency]">
    <option value="hourly" <?php selected($frequency, 'hourly'); ?>><?php _e('Hourly', 'rss-news-importer'); ?></option>
    <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>><?php _e('Twice Daily', 'rss-news-importer'); ?></option>
    <option value="daily" <?php selected($frequency, 'daily'); ?>><?php _e('Daily', 'rss-news-importer'); ?></option>
</select>