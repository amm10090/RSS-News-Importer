(function (window, document, $) {
    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const { useState, useEffect, useCallback, useRef } = React;

    function LogViewer({ translations, ajaxUrl, nonce }) {
        const [logs, setLogs] = useState([]);
        const [filteredLogs, setFilteredLogs] = useState([]);
        const [searchTerm, setSearchTerm] = useState('');
        const [filterOptions, setFilterOptions] = useState({
            dateRange: { start: null, end: null },
            logLevel: 'all',
            customFilters: []
        });
        const [sortOption, setSortOption] = useState({ field: 'date', order: 'desc' });
        const [currentPage, setCurrentPage] = useState(1);
        const [logsPerPage, setLogsPerPage] = useState(50);
        const [isAutoRefresh, setIsAutoRefresh] = useState(true);
        const [refreshInterval, setRefreshInterval] = useState(30000);
        const [theme, setTheme] = useState('light');
        const [statistics, setStatistics] = useState({});
        const observerRef = useRef(null);
        const logListRef = useRef(null);

        const fetchLogs = useCallback(async (params = {}) => {
            try {
                const response = await $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rss_news_importer_get_logs',
                        security: nonce,
                        ...params
                    }
                });
                if (response.success && Array.isArray(response.data)) {
                    return response.data;
                } else {
                    console.error('Invalid log data:', response);
                    return [];
                }
            } catch (error) {
                console.error('Ajax请求失败:', error);
                return [];
            }
        }, [ajaxUrl, nonce]);

        useEffect(() => {
            fetchLogs().then(data => {
                setLogs(Array.isArray(data) ? data : []);
            });
        }, [fetchLogs]);

        useEffect(() => {
            const fetchNewLogs = async () => {
                const newLogs = await fetchLogs({ after: logs[0]?.id });
                if (Array.isArray(newLogs) && newLogs.length > 0) {
                    setLogs(prevLogs => [...newLogs, ...prevLogs]);
                }
            };

            if (isAutoRefresh) {
                const intervalId = setInterval(fetchNewLogs, refreshInterval);
                return () => clearInterval(intervalId);
            }
        }, [isAutoRefresh, refreshInterval, logs, fetchLogs]);

        useEffect(() => {
            const options = {
                root: null,
                rootMargin: '20px',
                threshold: 1.0
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMoreLogs();
                }
            }, options);

            if (logListRef.current && logListRef.current.lastElementChild) {
                observer.observe(logListRef.current.lastElementChild);
            }

            observerRef.current = observer;

            return () => {
                if (observerRef.current) {
                    observerRef.current.disconnect();
                }
            };
        }, [filteredLogs]);

        const loadMoreLogs = useCallback(() => {
            setCurrentPage(prevPage => prevPage + 1);
        }, []);

        useEffect(() => {
            if (Array.isArray(logs) && logs.length > 0) {
                const filtered = logs.filter(log => {
                    if (!log || typeof log !== 'object') {
                        return false;
                    }
                    const matchesSearch = (log.message && log.message.toLowerCase().includes(searchTerm.toLowerCase())) ||
                        (log.date && log.date.toLowerCase().includes(searchTerm.toLowerCase()));
                    const matchesLevel = filterOptions.logLevel === 'all' || (log.level && log.level.toLowerCase() === filterOptions.logLevel.toLowerCase());
                    const matchesDateRange = (!filterOptions.dateRange.start || new Date(log.date) >= new Date(filterOptions.dateRange.start)) &&
                        (!filterOptions.dateRange.end || new Date(log.date) <= new Date(filterOptions.dateRange.end));
                    const matchesCustomFilters = filterOptions.customFilters.every(filter => {
                        const regex = new RegExp(filter.pattern, 'i');
                        return log[filter.field] && regex.test(log[filter.field]);
                    });

                    return matchesSearch && matchesLevel && matchesDateRange && matchesCustomFilters;
                });

                const sorted = [...filtered].sort((a, b) => {
                    const aValue = a[sortOption.field];
                    const bValue = b[sortOption.field];
                    return sortOption.order === 'asc' ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                });

                setFilteredLogs(sorted);
                setCurrentPage(1);
                updateStatistics(sorted);
            } else {
                setFilteredLogs([]);
            }
        }, [logs, searchTerm, filterOptions, sortOption]);

        const updateStatistics = (filteredLogs) => {
            const stats = {
                totalLogs: filteredLogs.length,
                errorCount: filteredLogs.filter(log => log.level && log.level.toLowerCase() === 'error').length,
                warningCount: filteredLogs.filter(log => log.level && log.level.toLowerCase() === 'warning').length,
                infoCount: filteredLogs.filter(log => log.level && log.level.toLowerCase() === 'info').length
            };
            setStatistics(stats);
        };

        const clearLogs = async () => {
            if (confirm(translations.confirm_clear)) {
                try {
                    const response = await $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'rss_news_importer_clear_logs',
                            security: nonce
                        }
                    });

                    if (response.success) {
                        setLogs([]);
                        setFilteredLogs([]);
                        alert(translations.success_clearing);
                    } else {
                        console.error('Failed to clear logs:', response);
                        alert(translations.error_clearing + ': ' + response.data);
                    }
                } catch (error) {
                    console.error('Error clearing logs:', error);
                    alert(translations.error_clearing);
                }
            }
        };

        const getLogTypeIcon = (type) => {
            switch ((type || '').toLowerCase()) {
                case 'info': return '⚪️';
                case 'debug': return '🔵';
                case 'warning': return '⚠️';
                case 'error': return '🔴';
                default: return '•';
            }
        };

        const getLogTypeColor = (type) => {
            switch ((type || '').toLowerCase()) {
                case 'info': return '#3498db';
                case 'debug': return '#2ecc71';
                case 'warning': return '#f39c12';
                case 'error': return '#e74c3c';
                default: return '#333333';
            }
        };

        const exportLogs = (format) => {
            let content;
            if (format === 'csv') {
                content = 'Date,Type,Message\n' + filteredLogs.map(log =>
                    `"${log.date || ''}","${log.level || ''}","${(log.message || '').replace(/"/g, '""')}"`
                ).join('\n');
            } else if (format === 'json') {
                content = JSON.stringify(filteredLogs, null, 2);
            }

            const blob = new Blob([content], { type: format === 'csv' ? 'text/csv;charset=utf-8;' : 'application/json' });
            const link = document.createElement('a');
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `logs.${format}`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        };

        const toggleTheme = () => {
            setTheme(prevTheme => prevTheme === 'light' ? 'dark' : 'light');
        };

        const renderLogItem = (log) => {
            if (!log || typeof log !== 'object') {
                return null;
            }
            return React.createElement('div', {
                key: log.id || Math.random(),
                className: "log-item",
                style: {
                    padding: '10px',
                    borderBottom: '1px solid #eee',
                    display: 'flex',
                    alignItems: 'center',
                    backgroundColor: theme === 'light' ? '#fff' : '#333',
                    color: theme === 'light' ? '#333' : '#fff'
                }
            }, [
                React.createElement('span', {
                    style: { flexBasis: '180px', color: theme === 'light' ? '#7f8c8d' : '#bdc3c7' }
                }, log.date || 'N/A'),
                React.createElement('span', {
                    style: {
                        flexBasis: '80px',
                        textAlign: 'center',
                        padding: '2px 6px',
                        borderRadius: '4px',
                        backgroundColor: getLogTypeColor(log.level),
                        color: '#fff',
                        marginRight: '10px'
                    }
                }, [getLogTypeIcon(log.level), ' ', log.level || 'Unknown']),
                React.createElement('span', { style: { flex: 1 } }, log.message || 'No message')
            ]);
        };

        return React.createElement('div', {
            style: {
                fontFamily: 'Arial, sans-serif',
                maxWidth: '100%',
                margin: '0 auto',
                padding: '20px',
                boxSizing: 'border-box',
                backgroundColor: theme === 'light' ? '#f0f0f0' : '#222',
                color: theme === 'light' ? '#333' : '#fff'
            }
        }, [
            React.createElement('h2', null, translations.log_viewer),
            React.createElement('div', { style: { marginBottom: '20px' } }, [
                React.createElement('input', {
                    type: "text",
                    placeholder: translations.search_logs,
                    value: searchTerm,
                    onChange: (e) => setSearchTerm(e.target.value),
                    style: { padding: '8px', marginRight: '10px' }
                }),
                React.createElement('select', {
                    value: filterOptions.logLevel,
                    onChange: (e) => setFilterOptions(prev => ({ ...prev, logLevel: e.target.value })),
                    style: { padding: '8px', marginRight: '10px' }
                }, [
                    React.createElement('option', { value: "all" }, translations.all_levels),
                    React.createElement('option', { value: "info" }, translations.info),
                    React.createElement('option', { value: "debug" }, translations.debug),
                    React.createElement('option', { value: "warning" }, translations.warning),
                    React.createElement('option', { value: "error" }, translations.error)
                ]),
                React.createElement('input', {
                    type: "date",
                    value: filterOptions.dateRange.start || '',
                    onChange: (e) => setFilterOptions(prev => ({ ...prev, dateRange: { ...prev.dateRange, start: e.target.value } })),
                    style: { padding: '8px', marginRight: '10px' }
                }),
                React.createElement('input', {
                    type: "date",
                    value: filterOptions.dateRange.end || '',
                    onChange: (e) => setFilterOptions(prev => ({ ...prev, dateRange: { ...prev.dateRange, end: e.target.value } })),
                    style: { padding: '8px', marginRight: '10px' }
                }),
                React.createElement('button', {
                    onClick: () => setIsAutoRefresh(prev => !prev),
                    style: { padding: '8px', marginRight: '10px' }
                }, isAutoRefresh ? translations.pause_refresh : translations.resume_refresh),
                React.createElement('button', {
                    onClick: toggleTheme,
                    style: { padding: '8px', marginRight: '10px' }
                }, theme === 'light' ? translations.dark_mode : translations.light_mode)
            ]),
            React.createElement('div', { style: { marginBottom: '20px' } }, [
                React.createElement('button', {
                    onClick: () => setSortOption({ field: 'date', order: sortOption.order === 'asc' ? 'desc' : 'asc' }),
                    style: { padding: '8px', marginRight: '10px' }
                }, `${translations.sort_by_date} (${sortOption.order === 'asc' ? '▲' : '▼'})`),
                React.createElement('button', {
                    onClick: () => exportLogs('csv'),
                    style: { padding: '8px', marginRight: '10px' }
                }, translations.export_csv),
                React.createElement('button', {
                    onClick: () => exportLogs('json'),
                    style: { padding: '8px', marginRight: '10px' }
                }, translations.export_json),
                React.createElement('button', {
                    onClick: clearLogs,
                    style: { padding: '8px', backgroundColor: '#e74c3c', color: 'white' }
                }, translations.clear_logs)
            ]),
            React.createElement('div', { style: { marginBottom: '20px' } }, [
                React.createElement('h3', null, translations.statistics),
                React.createElement('p', null, `${translations.total_logs}: ${statistics.totalLogs}`),
                React.createElement('p', null, `${translations.errors}: ${statistics.errorCount}`),
                React.createElement('p', null, `${translations.warnings}: ${statistics.warningCount}`),
                React.createElement('p', null, `${translations.info_logs}: ${statistics.infoCount}`)
            ]),
            React.createElement('div', {
                ref: logListRef,
                style: { border: '1px solid #ddd', borderRadius: '4px', overflow: 'hidden' }
            }, filteredLogs.slice(0, currentPage * logsPerPage).map(renderLogItem)),
            filteredLogs.length > currentPage * logsPerPage && React.createElement('button', {
                onClick: loadMoreLogs,
                style: { marginTop: '20px', padding: '10px' }
            }, translations.load_more)
        ]);
    }

    window.LogViewer = LogViewer;

    $(document).ready(() => {
        const rootElement = document.getElementById('log-viewer-root');
        if (rootElement) {
            ReactDOM.render(
                React.createElement(LogViewer, {
                    translations: rss_news_importer_ajax.i18n,
                    ajaxUrl: rss_news_importer_ajax.ajax_url,
                    nonce: rss_news_importer_ajax.nonce
                }),
                rootElement
            );
        } else {
        }
    });

})(window, document, jQuery);