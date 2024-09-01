(function ($) {
    'use strict';

    const rssImporter = {
        init() {
            this.cacheDom();
            this.bindEvents();
            this.initSortable();
            this.initTabs();
            this.loadSavedFeeds();
        },

        cacheDom() {
            this.feedsList = $('#rss-feeds-list');
            this.addFeedBtn = $('#add-feed');
            this.newFeedUrl = $('#new-feed-url');
            this.newFeedName = $('#new-feed-name');
            this.importNowBtn = $('#import-now');
            this.importResults = $('#import-results');
            this.importProgress = $('.progress-bar');
            this.importProgressText = $('.progress-text');
            this.importProgressContainer = $('.import-progress-container');
            this.form = $('#rss-news-importer-form');
            this.logViewerRoot = $('#log-viewer-root');
        },

        bindEvents() {
            this.addFeedBtn.on('click', () => this.addFeed());
            $(document).on('click', '.remove-feed', (e) => this.removeFeed($(e.currentTarget).closest('.feed-item')));
            $(document).on('click', '.preview-feed', (e) => this.previewFeed($(e.currentTarget).closest('.feed-item')));
            this.importNowBtn.on('click', () => this.importNow());
            this.form.on('submit', (e) => this.saveSettings(e));
        },

        initSortable() {
            this.feedsList.sortable({
                handle: '.handle',
                update: () => this.updateFeedOrder()
            });
        },

        initTabs() {
            $('.nav-tab-wrapper .nav-tab').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');
                $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content .tab-pane').removeClass('active');
                $(target).addClass('active');
        
                if (target === '#logs') {
                    try {
                        loadLogViewer();
                    } catch (error) {
                        console.error('Error loading LogViewer:', error);
                        $('#log-viewer-root').html('<p class="error-message">Error: Unable to load log viewer. Please check the console for more information.</p>');
                    }
                }
            });
        },

        loadSavedFeeds() {
            // 此方法已在PHP中实现，不需要在JS中重复
        },

        addFeed() {
            const feedUrl = this.newFeedUrl.val().trim();
            const feedName = this.newFeedName.val().trim();
            if (!feedUrl) return;

            this.ajaxRequest('rss_news_importer_add_feed', { feed_url: feedUrl, feed_name: feedName })
                .then((response) => {
                    if (response.success) {
                        const feedItem = $(response.data.html);
                        this.feedsList.append(feedItem);
                        feedItem.hide().fadeIn();
                        this.newFeedUrl.val('');
                        this.newFeedName.val('');
                        this.showFeedback(response.data.message, 'success');
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                })
                .catch(this.handleAjaxError.bind(this));
        },

        removeFeed(feedItem) {
            const feedUrl = feedItem.data('feed-url');
            if (!feedUrl) {
                console.error('Feed URL not found');
                return;
            }

            this.ajaxRequest('rss_news_importer_remove_feed', { feed_url: feedUrl })
                .then((response) => {
                    if (response.success) {
                        feedItem.fadeOut(() => {
                            feedItem.remove();
                            this.showFeedback(response.data, 'success');
                        });
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                })
                .catch(this.handleAjaxError.bind(this));
        },

        previewFeed(feedItem) {
            const feedUrl = feedItem.data('feed-url');
            if (!feedUrl) {
                console.error('Feed URL not found');
                return;
            }

            this.ajaxRequest('rss_news_importer_preview_feed', { feed_url: feedUrl })
                .then((response) => {
                    if (response.success) {
                        feedItem.find('.feed-preview').html(response.data).hide().fadeIn();
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                })
                .catch(this.handleAjaxError.bind(this));
        },

        updateFeedOrder() {
            const feedOrder = this.feedsList.sortable('toArray', { attribute: 'data-feed-url' });
            this.ajaxRequest('rss_news_importer_update_feed_order', { order: feedOrder })
                .then((response) => {
                    if (response.success) {
                        this.showFeedback(response.data, 'success');
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                })
                .catch(this.handleAjaxError.bind(this));
        },

        importNow() {
            this.importNowBtn.prop('disabled', true).text(rss_news_importer_ajax.i18n.importing_text);
            this.importProgressContainer.show();
            this.importProgress.css('width', '0%');
            this.importProgressText.text('0%');
            this.importResults.hide();

            this.ajaxRequest('rss_news_importer_import_now', {}, {
                xhr: () => {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (evt) => {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            this.importProgress.css('width', percentComplete + '%');
                            this.importProgressText.text(percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                }
            })
                .then((response) => {
                    if (response.success) {
                        this.importResults.html(response.data).fadeIn();
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                })
                .catch(this.handleAjaxError.bind(this))
                .finally(() => {
                    this.importNowBtn.prop('disabled', false).text(rss_news_importer_ajax.i18n.import_now_text);
                    setTimeout(() => {
                        this.importProgressContainer.hide();
                        this.importProgress.css('width', '0%');
                        this.importProgressText.text('0%');
                    }, 2000);
                });
        },

        saveSettings(e) {
            e.preventDefault();
            const formData = new FormData(this.form[0]);
            formData.append('action', 'rss_news_importer_save_settings');
            formData.append('security', rss_news_importer_ajax.nonce);

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.showFeedback(response.data, 'success');
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                },
                error: this.handleAjaxError.bind(this)
            });
        },

        showFeedback(message, type = 'success') {
            const feedback = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.wrap.rss-news-importer-admin').prepend(feedback);
            feedback.hide().fadeIn();
            setTimeout(() => {
                feedback.fadeOut(() => {
                    feedback.remove();
                });
            }, 3000);
        },

        ajaxRequest(action, data = {}, options = {}) {
            const defaultData = {
                action: action,
                security: rss_news_importer_ajax.nonce
            };

            const ajaxOptions = {
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: { ...defaultData, ...data },
                ...options
            };

            return $.ajax(ajaxOptions);
        },

        handleAjaxError(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
            this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
        }
    };

    // 当文档加载完成时初始化rssImporter对象
    $(document).ready(() => {
        rssImporter.init();
    });

})(jQuery);