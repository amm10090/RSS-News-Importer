<?php
// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 获取当前的设置
$options = get_option('rss_news_importer_options', array());

// 定义默认值
$defaults = array(
    'unwanted_elements' => array('script', 'style', 'iframe'),
    'unwanted_attributes' => array('style', 'onclick', 'onload'),
    'iframe_policy' => 'remove',
    'max_content_length' => 0,
    'base_url' => '',
    'remove_empty_paragraphs' => true,
    'convert_relative_urls' => true,
    'sanitize_html' => true,
    'remove_malicious_content' => true
);

// 合并默认值和保存的选项
$options = wp_parse_args($options, $defaults);

// 处理表单提交
if (isset($_POST['update_content_filter_settings'])) {
    check_admin_referer('rss_news_importer_content_filter_settings');

    $options['unwanted_elements'] = isset($_POST['unwanted_elements']) ? array_map('sanitize_text_field', $_POST['unwanted_elements']) : array();
    $options['unwanted_attributes'] = isset($_POST['unwanted_attributes']) ? array_map('sanitize_text_field', $_POST['unwanted_attributes']) : array();
    $options['iframe_policy'] = sanitize_text_field($_POST['iframe_policy']);
    $options['max_content_length'] = intval($_POST['max_content_length']);
    $options['base_url'] = esc_url_raw($_POST['base_url']);
    $options['remove_empty_paragraphs'] = isset($_POST['remove_empty_paragraphs']);
    $options['convert_relative_urls'] = isset($_POST['convert_relative_urls']);
    $options['sanitize_html'] = isset($_POST['sanitize_html']);
    $options['remove_malicious_content'] = isset($_POST['remove_malicious_content']);

    update_option('rss_news_importer_options', $options);
    add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('内容过滤设置已更新。', 'rss-news-importer'), 'updated');
}

settings_errors('rss_news_importer_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('rss_news_importer_content_filter_settings'); ?>

        <h2><?php _e('内容过滤设置', 'rss-news-importer'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="unwanted_elements"><?php _e('要移除的HTML元素', 'rss-news-importer'); ?></label></th>
                <td>
                    <?php
                    $common_elements = array('script', 'style', 'iframe', 'form', 'object', 'embed');
                    foreach ($common_elements as $element) {
                        echo '<label><input type="checkbox" name="unwanted_elements[]" value="' . esc_attr($element) . '" ' . checked(in_array($element, $options['unwanted_elements']), true, false) . '> ' . esc_html($element) . '</label><br>';
                    }
                    ?>
                    <p class="description"><?php _e('选择要从导入的内容中移除的HTML元素。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="unwanted_attributes"><?php _e('要移除的HTML属性', 'rss-news-importer'); ?></label></th>
                <td>
                    <?php
                    $common_attributes = array('style', 'onclick', 'onload', 'onerror', 'onmouseover');
                    foreach ($common_attributes as $attr) {
                        echo '<label><input type="checkbox" name="unwanted_attributes[]" value="' . esc_attr($attr) . '" ' . checked(in_array($attr, $options['unwanted_attributes']), true, false) . '> ' . esc_html($attr) . '</label><br>';
                    }
                    ?>
                    <p class="description"><?php _e('选择要从HTML元素中移除的属性。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="iframe_policy"><?php _e('iframe 处理策略', 'rss-news-importer'); ?></label></th>
                <td>
                    <select name="iframe_policy" id="iframe_policy">
                        <option value="remove" <?php selected($options['iframe_policy'], 'remove'); ?>><?php _e('移除', 'rss-news-importer'); ?></option>
                        <option value="placeholder" <?php selected($options['iframe_policy'], 'placeholder'); ?>><?php _e('替换为占位符', 'rss-news-importer'); ?></option>
                        <option value="allow" <?php selected($options['iframe_policy'], 'allow'); ?>><?php _e('保留', 'rss-news-importer'); ?></option>
                    </select>
                    <p class="description"><?php _e('选择如何处理导入内容中的iframe。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="max_content_length"><?php _e('最大内容长度', 'rss-news-importer'); ?></label></th>
                <td>
                    <input type="number" name="max_content_length" id="max_content_length" value="<?php echo esc_attr($options['max_content_length']); ?>" min="0">
                    <p class="description"><?php _e('设置导入内容的最大字符数。设为0表示不限制。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="base_url"><?php _e('基础URL', 'rss-news-importer'); ?></label></th>
                <td>
                    <input type="url" name="base_url" id="base_url" value="<?php echo esc_url($options['base_url']); ?>" class="regular-text">
                    <p class="description"><?php _e('设置用于转换相对URL的基础URL。留空表示不转换。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('其他设置', 'rss-news-importer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="remove_empty_paragraphs" <?php checked($options['remove_empty_paragraphs'], true); ?>>
                        <?php _e('移除空段落', 'rss-news-importer'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="convert_relative_urls" <?php checked($options['convert_relative_urls'], true); ?>>
                        <?php _e('转换相对URL为绝对URL', 'rss-news-importer'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="sanitize_html" <?php checked($options['sanitize_html'], true); ?>>
                        <?php _e('净化HTML（移除不安全的元素和属性）', 'rss-news-importer'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="remove_malicious_content" <?php checked($options['remove_malicious_content'], true); ?>>
                        <?php _e('移除潜在的恶意内容', 'rss-news-importer'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(__('保存设置', 'rss-news-importer'), 'primary', 'update_content_filter_settings'); ?>
    </form>
</div>