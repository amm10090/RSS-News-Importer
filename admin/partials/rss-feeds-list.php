<div id="rss-feeds-list">
    <?php foreach ($rss_feeds as $index => $feed): ?>
        <div class="feed-item">
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][<?php echo $index; ?>][url]" value="<?php echo esc_url($feed['url']); ?>">
            <input type="text" name="<?php echo $this->option_name; ?>[rss_feeds][<?php echo $index; ?>][name]" value="<?php echo esc_attr($feed['name']); ?>">
        </div>
    <?php endforeach; ?>
</div>