<?php
$thumb_size = isset($options['thumb_size']) ? $options['thumb_size'] : 'thumbnail';
$force_thumb = isset($options['force_thumb']) ? $options['force_thumb'] : 0;
?>
<p>
    <label><?php _e('Thumbnail Size:', 'rss-news-importer'); ?></label>
    <select name="<?php echo $this->option_name; ?>[thumb_size]">
        <?php
        $sizes = get_intermediate_image_sizes();
        foreach ($sizes as $size) {
            echo '<option value="' . $size . '" ' . selected($thumb_size, $size, false) . '>' . $size . '</option>';
        }
        ?>
    </select>
</p>
<p>
    <label>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[force_thumb]" value="1" <?php checked($force_thumb, 1); ?>>
        <?php _e('Force thumbnail generation', 'rss-news-importer'); ?>
    </label>
</p>