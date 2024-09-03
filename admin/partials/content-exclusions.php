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

// 添加这个用于验证的函数
function validate_content_exclusion_settings($input)
{
    $valid = array();
    $errors = array();

    // 验证不需要的元素
    $valid['unwanted_elements'] = isset($input['unwanted_elements']) ? array_map('sanitize_text_field', $input['unwanted_elements']) : array();

    // 验证不需要的属性
    $valid['unwanted_attributes'] = isset($input['unwanted_attributes']) ? array_map('sanitize_text_field', $input['unwanted_attributes']) : array();

    // 验证iframe策略
    $valid['iframe_policy'] = sanitize_text_field($input['iframe_policy']);
    if (!in_array($valid['iframe_policy'], array('remove', 'placeholder', 'allow'))) {
        $errors[] = __('选择的iframe策略无效。', 'rss-news-importer');
    }

    // 验证最大内容长度
    $valid['max_content_length'] = intval($input['max_content_length']);
    if ($valid['max_content_length'] < 0) {
        $errors[] = __('最大内容长度必须是非负数。', 'rss-news-importer');
    }

    // 验证基础URL
    $valid['base_url'] = esc_url_raw($input['base_url']);

    // 验证布尔设置
    $valid['remove_empty_paragraphs'] = isset($input['remove_empty_paragraphs']);
    $valid['convert_relative_urls'] = isset($input['convert_relative_urls']);
    $valid['sanitize_html'] = isset($input['sanitize_html']);
    $valid['remove_malicious_content'] = isset($input['remove_malicious_content']);

    if (!empty($errors)) {
        foreach ($errors as $error) {
            add_settings_error('rss_news_importer_messages', 'rss_news_importer_error', $error, 'error');
        }
        return false;
    }

    return $valid;
}

// 处理表单提交
if (isset($_POST['update_content_filter_settings'])) {
    check_admin_referer('rss_news_importer_content_filter_settings');

    $input = array(
        'unwanted_elements' => isset($_POST['unwanted_elements']) ? $_POST['unwanted_elements'] : array(),
        'unwanted_attributes' => isset($_POST['unwanted_attributes']) ? $_POST['unwanted_attributes'] : array(),
        'iframe_policy' => $_POST['iframe_policy'],
        'max_content_length' => $_POST['max_content_length'],
        'base_url' => $_POST['base_url'],
        'remove_empty_paragraphs' => isset($_POST['remove_empty_paragraphs']),
        'convert_relative_urls' => isset($_POST['convert_relative_urls']),
        'sanitize_html' => isset($_POST['sanitize_html']),
        'remove_malicious_content' => isset($_POST['remove_malicious_content'])
    );

    $valid_input = validate_content_exclusion_settings($input);

    if ($valid_input) {
        $options = array_merge($options, $valid_input);
        update_option('rss_news_importer_options', $options);
        add_settings_error('rss_news_importer_messages', 'rss_news_importer_message', __('内容过滤设置已更新。', 'rss-news-importer'), 'updated');
    }
}

settings_errors('rss_news_importer_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('rss_news_importer_options');
        do_settings_sections('rss_news_importer');
        ?>

        <h2><?php _e('内容过滤设置', 'rss-news-importer'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="unwanted_elements"><?php _e('要移除的HTML元素', 'rss-news-importer'); ?></label></th>
                <td>
                    <?php
                    $common_elements = array('script', 'style', 'iframe', 'form', 'object', 'embed');
                    foreach ($common_elements as $element) {
                        echo '<label><input type="checkbox" name="rss_news_importer_options[unwanted_elements][]" value="' . esc_attr($element) . '" ' . checked(in_array($element, $options['unwanted_elements']), true, false) . '> ' . esc_html($element) . '</label><br>';
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
                        echo '<label><input type="checkbox" name="rss_news_importer_options[unwanted_attributes][]" value="' . esc_attr($attr) . '" ' . checked(in_array($attr, $options['unwanted_attributes']), true, false) . '> ' . esc_html($attr) . '</label><br>';
                    }
                    ?>
                    <p class="description"><?php _e('选择要从HTML元素中移除的属性。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="iframe_policy"><?php _e('iframe 处理策略', 'rss-news-importer'); ?></label></th>
                <td>
                    <select name="rss_news_importer_options[iframe_policy]" id="iframe_policy">
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
                    <input type="number" name="rss_news_importer_options[max_content_length]" id="max_content_length" value="<?php echo esc_attr($options['max_content_length']); ?>" min="0">
                    <p class="description"><?php _e('设置导入内容的最大字符数。设为0表示不限制。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="base_url"><?php _e('基础URL', 'rss-news-importer'); ?></label></th>
                <td>
                    <input type="url" name="rss_news_importer_options[base_url]" id="base_url" value="<?php echo esc_url($options['base_url']); ?>" class="regular-text">
                    <p class="description"><?php _e('设置用于转换相对URL的基础URL。留空表示不转换。', 'rss-news-importer'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('其他设置', 'rss-news-importer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="rss_news_importer_options[remove_empty_paragraphs]" <?php checked($options['remove_empty_paragraphs'], true); ?>>
                        <?php _e('移除空段落', 'rss-news-importer'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="rss_news_importer_options[convert_relative_urls]" <?php checked($options['convert_relative_urls'], true); ?>>
                        <?php _e('转换相对URL为绝对URL', 'rss-news-importer'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="rss_news_importer_options[sanitize_html]" <?php checked($options['sanitize_html'], true); ?>>
                        <?php _e('净化HTML（移除不安全的元素和属性）', 'rss-news-importer'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="rss_news_importer_options[remove_malicious_content]" <?php checked($options['remove_malicious_content'], true); ?>>
                        <?php _e('移除潜在的恶意内容', 'rss-news-importer'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(__('保存设置', 'rss-news-importer')); ?>
    </form>
</div>