<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option($this->option_name);
$cache_expiration = isset($options['cache_expiration']) ? intval($options['cache_expiration']) : 3600;
?>

<p>
    <label for="cache_expiration"><?php _e('缓存过期时间（秒）:', 'rss-news-importer'); ?></label>
    <input type="number" id="cache_expiration" name="<?php echo $this->option_name; ?>[cache_expiration]" value="<?php echo esc_attr($cache_expiration); ?>" min="0" step="1">
</p>
<p class="description"><?php _e('设置RSS源数据的缓存时间。0表示不使用缓存。', 'rss-news-importer'); ?></p>

<?php
//可以在这里添加一个清理缓存的按钮
?>
<p>
    <button type="button" id="clear_cache" class="button"><?php _e('清理缓存', 'rss-news-importer'); ?></button>
</p>
<script>
    jQuery(document).ready(function($) {
        $('#clear_cache').on('click', function() {
            // 这里添加AJAX请求来清理缓存
            alert('缓存清理功能尚未实现');
        });
    });
</script>