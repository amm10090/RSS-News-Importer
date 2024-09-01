<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="log-viewer-root"></div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined' && typeof LogViewer !== 'undefined') {
            ReactDOM.render(
                React.createElement(LogViewer),
                document.getElementById('log-viewer-root')
            );
        } else {
            console.error('React, ReactDOM, or LogViewer is not loaded. Make sure to enqueue these scripts.');
            $('#log-viewer-root').html('<p class="error-message">Error: Unable to load log viewer. Please check the console for more information.</p>');
        }
    });
</script>