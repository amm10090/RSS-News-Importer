<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <form action="options.php" method="post">
    <?php
        settings_fields( $this->plugin_name );
        do_settings_sections( $this->plugin_name );
        $feed_urls = get_option( $this->plugin_name . '_feed_urls', array() );
        $import_frequency = get_option( $this->plugin_name . '_import_frequency', 'hourly' );
        $post_status = get_option( $this->plugin_name . '_post_status', 'draft' );
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">RSS Feed URLs</th>
            <td>
                <div id="feed-urls">
                    <?php foreach ( $feed_urls as $index => $url ) : ?>
                        <p>
                            <input type="text" name="<?php echo $this->plugin_name; ?>_feed_urls[]" value="<?php echo esc_url( $url ); ?>" class="regular-text" />
                            <button type="button" class="button remove-url">Remove</button>
                        </p>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="add-url">Add New URL</button>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Import Frequency</th>
            <td>
                <select name="<?php echo $this->plugin_name; ?>_import_frequency">
                    <option value="hourly" <?php selected( $import_frequency, 'hourly' ); ?>>Hourly</option>
                    <option value="twicedaily" <?php selected( $import_frequency, 'twicedaily' ); ?>>Twice Daily</option>
                    <option value="daily" <?php selected( $import_frequency, 'daily' ); ?>>Daily</option>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Default Post Status</th>
            <td>
                <select name="<?php echo $this->plugin_name; ?>_post_status">
                    <option value="draft" <?php selected( $post_status, 'draft' ); ?>>Draft</option>
                    <option value="publish" <?php selected( $post_status, 'publish' ); ?>>Published</option>
                </select>
            </td>
        </tr>
    </table>
    <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
<script>
jQuery(document).ready(function($) {
    $('#add-url').on('click', function() {
        $('#feed-urls').append('<p><input type="text" name="<?php echo $this->plugin_name; ?>_feed_urls[]" value="" class="regular-text" /> <button type="button" class="button remove-url">Remove</button></p>');
    });
    
    $('#feed-urls').on('click', '.remove-url', function() {
        $(this).parent().remove();
    });
});
</script>