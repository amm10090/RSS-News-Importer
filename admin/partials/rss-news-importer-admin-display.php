<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap rss-news-importer-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="rss-importer-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#feeds" class="nav-tab nav-tab-active"><?php _e('RSS Feeds', 'rss-news-importer'); ?></a>
            <a href="#settings" class="nav-tab"><?php _e('Settings', 'rss-news-importer'); ?></a>
        </nav>

        <div class="tab-content">
            <div id="feeds" class="tab-pane active">
                <div class="card full-width">
                    <h2 class="title"><?php _e('Manage RSS Feeds', 'rss-news-importer'); ?></h2>
                    <div class="inside">
                        <div id="rss-feeds-list" class="feed-grid">
                            <?php
                            $options = get_option($this->option_name);
                            $feeds = isset($options['rss_feeds']) ? $options['rss_feeds'] : array();
                            if (empty($feeds)) {
                                echo '<p class="no-feeds">' . __('No RSS feeds added yet.', 'rss-news-importer') . '</p>';
                            } else {
                                foreach ($feeds as $index => $feed) :
                                    $feed_url = isset($feed['url']) ? $feed['url'] : '';
                                    $feed_name = isset($feed['name']) ? $feed['name'] : '';
                                    if (empty($feed_url)) continue;
                            ?>
                                    <div class="feed-item" data-feed-url="<?php echo esc_attr($feed_url); ?>">
                                        <div class="feed-item-content">
                                            <span class="dashicons dashicons-menu handle"></span>
                                            <input type="text" value="<?php echo esc_url($feed_url); ?>" readonly class="feed-url">
                                            <input type="text" value="<?php echo esc_attr($feed_name); ?>" placeholder="<?php _e('Feed Name (optional)', 'rss-news-importer'); ?>" class="feed-name">
                                            <div class="feed-actions">
                                                <button class="button button-secondary preview-feed" title="<?php _e('Preview', 'rss-news-importer'); ?>">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                                <button class="button button-secondary remove-feed" title="<?php _e('Remove', 'rss-news-importer'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="feed-preview"></div>
                                    </div>
                            <?php
                                endforeach;
                            }
                            ?>
                        </div>
                        <div class="rss-feed-actions">
                            <input type="text" id="new-feed-url" placeholder="<?php _e('Enter new feed URL', 'rss-news-importer'); ?>">
                            <input type="text" id="new-feed-name" placeholder="<?php _e('Enter feed name (optional)', 'rss-news-importer'); ?>">
                            <button id="add-feed" class="button button-primary">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php _e('Add Feed', 'rss-news-importer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="settings" class="tab-pane">
                <form method="post" action="javascript:void(0);" id="rss-news-importer-form">
                    <?php
                    settings_fields($this->plugin_name);
                    do_settings_sections($this->plugin_name);
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
    </div>
</div>