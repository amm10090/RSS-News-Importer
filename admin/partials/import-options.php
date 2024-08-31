<?php
$category = isset($options['import_category']) ? $options['import_category'] : '';
$author = isset($options['import_author']) ? $options['import_author'] : get_current_user_id();
?>
<p>
    <label><?php _e('Default Category:', 'rss-news-importer'); ?></label>
    <?php
    wp_dropdown_categories(array(
        'name' => $this->option_name . '[import_category]',
        'selected' => $category,
        'show_option_none' => __('Select a category', 'rss-news-importer'),
        'option_none_value' => '',
        'hide_empty' => 0,
    ));
    ?>
</p>
<p>
    <label><?php _e('Default Author:', 'rss-news-importer'); ?></label>
    <?php
    wp_dropdown_users(array(
        'name' => $this->option_name . '[import_author]',
        'selected' => $author,
    ));
    ?>
</p>