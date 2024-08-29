(function ($) {
    'use strict';

    const rssImporter = {
        // 初始化函数
        init() {
            this.cacheDom();
            this.bindEvents();
            this.initSortable();
            this.initTabs();
            this.loadSavedFeeds();
        },

        // 缓存DOM元素
        cacheDom() {
            this.feedsList = $('#rss-feeds-list');
            this.addFeedBtn = $('#add-feed');
            this.newFeedUrl = $('#new-feed-url');
            this.newFeedName = $('#new-feed-name');
            this.feedPreview = $('#feed-preview');
            this.importNowBtn = $('#import-now');
            this.importResults = $('#import-results');
            this.importProgress = $('#import-progress');
            this.form = $('#rss-news-importer-form');
            this.logViewerRoot = $('#log-viewer-root');
        },

        // 绑定事件
        bindEvents() {
            this.addFeedBtn.on('click', () => this.addFeed());
            $(document).on('click', '#rss-feeds-list .remove-feed', (e) => {
                e.preventDefault();
                this.removeFeed($(e.currentTarget).closest('.feed-item'));
            });

            $(document).on('click', '#rss-feeds-list .preview-feed', (e) => {
                e.preventDefault();
                this.previewFeed($(e.currentTarget).closest('.feed-item'));
            });
            this.importNowBtn.on('click', () => this.importNow());
            $('#view-logs').on('click', () => this.viewLogs());
            $('.run-now').on('click', (e) => this.runTask($(e.target)));
            this.form.on('submit', (e) => this.saveSettings(e));
        },

        // 初始化可排序功能
        initSortable() {
            this.feedsList.sortable({
                handle: '.handle',
                update: (event, ui) => this.updateFeedOrder()
            });
        },

        // 初始化选项卡
        initTabs() {
            $('.nav-tab-wrapper .nav-tab').on('click', function (e) {
                e.preventDefault();
                const target = $(this).attr('href');
                $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content .tab-pane').removeClass('active');
                $(target).addClass('active');
                if (target === '#logs') {
                    rssImporter.viewLogs();
                    rssImporter.loadLogViewer();
                }
            });
        },

        // 加载已保存的RSS源
        loadSavedFeeds() {
            this.feedsList.find('.feed-item').each(function () {
                const feedUrl = $(this).find('.feed-url').val();
                const feedName = $(this).find('.feed-name').val();
                console.log('Loaded feed:', feedUrl, feedName);
            });
        },

        // 添加新的RSS源
        addFeed() {
            const feedUrl = this.newFeedUrl.val().trim();
            const feedName = this.newFeedName.val().trim();
            if (!feedUrl) return;

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_add_feed',
                    feed_url: feedUrl,
                    feed_name: feedName,
                    security: rss_news_importer_ajax.nonce
                },
                success: (response) => {
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
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        },

        // 移除RSS源
        removeFeed(feedItem) {
            const feedUrl = feedItem.find('.feed-url').val();
            if (!feedUrl) {
                console.error('Feed URL not found');
                return;
            }

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_remove_feed',
                    feed_url: feedUrl,
                    security: rss_news_importer_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        feedItem.fadeOut(() => {
                            feedItem.remove();
                            this.showFeedback(response.data, 'success');
                        });
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        },

        // 预览RSS源
        previewFeed(feedItem) {
            const feedUrl = feedItem.find('.feed-url').val();
            if (!feedUrl) {
                console.error('Feed URL not found');
                return;
            }

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_preview_feed',
                    feed_url: feedUrl,
                    security: rss_news_importer_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        feedItem.find('.feed-preview').html(response.data).hide().fadeIn();
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        },

        // 更新RSS源顺序
        updateFeedOrder() {
            const feedOrder = this.feedsList.sortable('toArray', { attribute: 'data-feed-url' });
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_update_feed_order',
                    order: feedOrder,
                    security: rss_news_importer_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showFeedback(response.data, 'success');
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                }
            });
        },

        // 立即导入RSS源
        importNow() {
            this.importNowBtn.prop('disabled', true).text(rss_news_importer_ajax.i18n.importing_text);
            this.importProgress.show().css('width', '0%');

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_import_now',
                    security: rss_news_importer_ajax.nonce
                },
                xhr: () => {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (evt) => {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            this.importProgress.css('width', percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: (response) => {
                    if (response.success) {
                        this.importResults.html(response.data).hide().fadeIn();
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                    this.importNowBtn.prop('disabled', false).text(rss_news_importer_ajax.i18n.import_now_text);
                    this.importProgress.hide();
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                    this.importNowBtn.prop('disabled', false).text(rss_news_importer_ajax.i18n.import_now_text);
                    this.importProgress.hide();
                }
            });
        },

        // 查看日志
        viewLogs() {
            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_view_logs',
                    security: rss_news_importer_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#import-logs').html(response.data).hide().fadeIn();
                    } else {
                        this.showFeedback('加载日志时出错。', 'error');
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback('加载日志时出错。', 'error');
                }
            });
        },

        // 加载日志查看器
        loadLogViewer() {
            if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined' && typeof LogViewer !== 'undefined') {
                ReactDOM.render(
                    React.createElement(LogViewer),
                    this.logViewerRoot[0]
                );
            } else {
                console.error('React, ReactDOM, or LogViewer component is not loaded. Make sure to enqueue these scripts.');
                this.logViewerRoot.html('<p class="error-message">Error: Unable to load log viewer. Please check the console for more information.</p>');
            }
        },

        // 运行任务
        runTask(button) {
            const taskName = button.data('task');
            button.prop('disabled', true).text(rss_news_importer_ajax.i18n.running_text);

            $.ajax({
                url: rss_news_importer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rss_news_importer_run_task',
                    task_name: taskName,
                    security: rss_news_importer_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showFeedback(response.data, 'success');
                    } else {
                        this.showFeedback(response.data, 'error');
                    }
                    button.prop('disabled', false).text(rss_news_importer_ajax.i18n.run_now_text);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback(rss_news_importer_ajax.i18n.error_text, 'error');
                    button.prop('disabled', false).text(rss_news_importer_ajax.i18n.run_now_text);
                }
            });
        },

        // 保存设置
        saveSettings(e) {
            e.preventDefault();
            const formData = this.form.serialize();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: (response) => {
                    console.log('Save success:', response);
                    this.showFeedback('设置已保存', 'success');
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', textStatus, errorThrown);
                    this.showFeedback('保存设置时出错', 'error');
                }
            });
        },

        // 显示反馈信息
        showFeedback(message, type = 'success') {
            const feedback = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
            $('.wrap.rss-news-importer-admin').prepend(feedback);
            feedback.hide().fadeIn();
            setTimeout(() => {
                feedback.fadeOut(() => {
                    feedback.remove();
                });
            }, 3000);
        }
    };

    // 当文档加载完成时初始化rssImporter对象
    $(document).ready(() => {
        rssImporter.init();
    });

})(jQuery);