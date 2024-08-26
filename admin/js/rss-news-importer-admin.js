(function($) {
    'use strict';

    $(function() {
        $('#add-feed').on('click', function() {
            var newField = '<p><input type="text" name="rss_news_importer_options[rss_feeds][]" value="" class="regular-text" /> <button type="button" class="button remove-feed">Remove</button></p>';
            $('#rss-feeds').append(newField);
        });

        $('#rss-feeds').on('click', '.remove-feed', function() {
            $(this).parent().remove();
        });

        $('#import-now').on('click', function() {
            var button = $(this);
            button.prop('disabled', true);
            $('#import-result').text('Importing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_import_now'
                },
                success: function(response) {
                    $('#import-result').html(response);
                },
                error: function() {
                    $('#import-result').text('An error occurred during import.');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    });
})(jQuery);