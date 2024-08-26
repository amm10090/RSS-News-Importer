(function($) {
    'use strict';

    $(function() {
        // RSS源添加和删除功能保持不变

        $('#import-now').on('click', function() {
            var button = $(this);
            button.prop('disabled', true);
            $('#import-result').text(rss_news_importer_ajax.importing_text);

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_import_now',
                    security: rss_news_importer_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#import-result').html('<div class="notice notice-success">' + response.data + '</div>');
                    } else {
                        $('#import-result').html('<div class="notice notice-error">' + response.data + '</div>');
                    }
                },
                error: function() {
                    $('#import-result').html('<div class="notice notice-error">' + rss_news_importer_ajax.error_text + '</div>');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    });
})(jQuery);