(function (window, document, $) {
    const React = window.React;
    const ReactDOM = window.ReactDOM;
    const { useState, useEffect, useCallback, useRef } = React;

    function LogViewer({ translations, ajaxUrl, nonce }) {
        const [logs, setLogs] = useState([]);
        const [filteredLogs, setFilteredLogs] = useState([]);
        const [searchTerm, setSearchTerm] = useState('');
        const [filterOptions, setFilterOptions] = useState({
            logLevel: 'all',
            dateRange: { start: '', end: '' }
        });
        const [sortOption, setSortOption] = useState({ field: 'date', order: 'desc' });
        const [currentPage, setCurrentPage] = useState(1);
        const [logsPerPage] = useState(50);
        const [statistics, setStatistics] = useState({
            totalLogs: 0,
            errorCount: 0,
            warningCount: 0,
            infoCount: 0
        });
        const [isAutoRefresh, setIsAutoRefresh] = useState(false);
        const [theme, setTheme] = useState('light');
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
                console.error('Ajax request failed:', error);
                return [];
            }
        }, [ajaxUrl, nonce]);

        useEffect(() => {
            fetchLogs().then(setLogs);
        }, [fetchLogs]);

        useEffect(() => {
            let intervalId;
            if (isAutoRefresh) {
                intervalId = setInterval(() => {
                    fetchLogs().then(setLogs);
                }, 30000); // 每30秒刷新一次
            }
            return () => {
                if (intervalId) {
                    clearInterval(intervalId);
                }
            };
        }, [isAutoRefresh, fetchLogs]);

        useEffect(() => {
            const filtered = logs.filter(log => {
                const matchesSearch = log.message.toLowerCase().includes(searchTerm.toLowerCase());
                const matchesLevel = filterOptions.logLevel === 'all' || log.level === filterOptions.logLevel;
                const matchesDate = (!filterOptions.dateRange.start || new Date(log.date) >= new Date(filterOptions.dateRange.start)) &&
                                    (!filterOptions.dateRange.end || new Date(log.date) <= new Date(filterOptions.dateRange.end));
                return matchesSearch && matchesLevel && matchesDate;
            });

            const sorted = [...filtered].sort((a, b) => {
                if (sortOption.field === 'date') {
                    return sortOption.order === 'asc' 
                        ? new Date(a.date) - new Date(b.date)
                        : new Date(b.date) - new Date(a.date);
                }
                return 0;
            });

            setFilteredLogs(sorted);
            setStatistics({
                totalLogs: sorted.length,
                errorCount: sorted.filter(log => log.level === 'error').length,
                warningCount: sorted.filter(log => log.level === 'warning').length,
                infoCount: sorted.filter(log => log.level === 'info').length
            });
        }, [logs, searchTerm, filterOptions, sortOption]);

        const loadMoreLogs = () => {
            setCurrentPage(prevPage => prevPage + 1);
        };

        const exportLogs = async (format) => {
            try {
                const response = await $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rss_news_importer_export_logs',
                        security: nonce,
                        format: format
                    }
                });
                if (response.success) {
                    const blob = new Blob([response.data], { type: format === 'csv' ? 'text/csv' : 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `rss_news_importer_logs.${format}`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert(translations.error_exporting);
                }
            } catch (error) {
                console.error('Export failed:', error);
                alert(translations.error_exporting);
            }
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
                        alert(translations.success_clearing);
                    } else {
                        alert(translations.error_clearing);
                    }
                } catch (error) {
                    console.error('Clear logs failed:', error);
                    alert(translations.error_clearing);
                }
            }
        };

        const toggleTheme = () => {
            setTheme(prevTheme => prevTheme === 'light' ? 'dark' : 'light');
        };

        const getLogTypeIcon = (level) => {
            switch (level) {
                case 'error': return '🔴';
                case 'warning': return '🟠';
                case 'info': return '🔵';
                default: return '⚪';
            }
        };

        const refreshLogs = async () => {
            try {
                const response = await $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'rss_news_importer_refresh_logs',
                        security: nonce
                    }
                });
                if (response.success) {
                    setLogs(response.data);
                    alert(translations.logs_refreshed);
                } else {
                    alert(translations.error_refreshing);
                }
            } catch (error) {
                console.error('Ajax request failed:', error);
                alert(translations.error_refreshing);
            }
        };

        return React.createElement('div', {
            className: `log-viewer ${theme}`,
            style: {
                fontFamily: 'Arial, sans-serif',
                maxWidth: '100%',
                margin: '0 auto',
                padding: '20px',
                boxSizing: 'border-box',
                backgroundColor: theme === 'light' ? '#f0f0f0' : '#222',
                color: theme === 'light' ? '#333' : '#fff',
                transition: 'all 0.3s ease'
            }
        }, [
            React.createElement('h2', { key: 'title', style: { marginBottom: '20px' } }, translations.log_viewer),
            React.createElement('div', { 
                key: 'controls', 
                style: { 
                    marginBottom: '20px',
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '10px',
                    alignItems: 'center'
                } 
            }, [
                React.createElement('input', {
                    key: 'search',
                    type: "text",
                    placeholder: translations.search_logs,
                    value: searchTerm,
                    onChange: (e) => setSearchTerm(e.target.value),
                    style: { 
                        padding: '8px', 
                        flex: '1 1 200px',
                        borderRadius: '4px',
                        border: '1px solid #ccc'
                    }
                }),
                React.createElement('select', {
                    key: 'level-filter',
                    value: filterOptions.logLevel,
                    onChange: (e) => setFilterOptions(prev => ({ ...prev, logLevel: e.target.value })),
                    style: { 
                        padding: '8px', 
                        flex: '0 1 150px',
                        borderRadius: '4px',
                        border: '1px solid #ccc'
                    }
                }, [
                    React.createElement('option', { key: 'all', value: "all" }, translations.all_levels),
                    React.createElement('option', { key: 'info', value: "info" }, translations.info),
                    React.createElement('option', { key: 'warning', value: "warning" }, translations.warning),
                    React.createElement('option', { key: 'error', value: "error" }, translations.error)
                ]),
                React.createElement('input', {
                    key: 'date-start',
                    type: "date",
                    value: filterOptions.dateRange.start || '',
                    onChange: (e) => setFilterOptions(prev => ({ ...prev, dateRange: { ...prev.dateRange, start: e.target.value } })),
                    style: { 
                        padding: '8px', 
                        flex: '0 1 auto',
                        borderRadius: '4px',
                        border: '1px solid #ccc'
                    }
                }),
                React.createElement('input', {
                    key: 'date-end',
                    type: "date",
                    value: filterOptions.dateRange.end || '',
                    onChange: (e) => setFilterOptions(prev => ({ ...prev, dateRange: { ...prev.dateRange, end: e.target.value } })),
                    style: { 
                        padding: '8px', 
                        flex: '0 1 auto',
                        borderRadius: '4px',
                        border: '1px solid #ccc'
                    }
                }),
                React.createElement('button', {
                    key: 'refresh-toggle',
                    onClick: () => setIsAutoRefresh(prev => !prev),
                    style: { 
                        padding: '8px', 
                        flex: '0 1 auto',
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: isAutoRefresh ? '#4CAF50' : '#f44336',
                        color: 'white',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, isAutoRefresh ? translations.pause_refresh : translations.resume_refresh),
                React.createElement('button', {
                    key: 'theme-toggle',
                    onClick: toggleTheme,
                    style: { 
                        padding: '8px', 
                        flex: '0 1 auto',
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: theme === 'light' ? '#333' : '#f0f0f0',
                        color: theme === 'light' ? '#fff' : '#333',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, theme === 'light' ? translations.dark_mode : translations.light_mode)
            ]),
            React.createElement('div', { 
                key: 'actions', 
                style: { 
                    marginBottom: '20px',
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '10px'
                } 
            }, [
                React.createElement('button', {
                    key: 'sort-toggle',
                    onClick: () => setSortOption({ field: 'date', order: sortOption.order === 'asc' ? 'desc' : 'asc' }),
                    style: { 
                        padding: '8px', 
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: '#2196F3',
                        color: 'white',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, `${translations.sort_by_date} (${sortOption.order === 'asc' ? translations.ascending : translations.descending})`),
                React.createElement('button', {
                    key: 'refresh-logs',
                    onClick: refreshLogs,
                    style: { 
                        padding: '8px', 
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: '#4CAF50',
                        color: 'white',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, translations.refresh_logs),
                React.createElement('button', {
                    key: 'export-csv',
                    onClick: () => exportLogs('csv'),
                    style: { 
                        padding: '8px', 
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: '#FF9800',
                        color: 'white',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, translations.export_csv),
                React.createElement('button', {
                    key: 'export-json',
                    onClick: () => exportLogs('json'),
                    style: { 
                        padding: '8px', 
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: '#9C27B0',
                        color: 'white',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, translations.export_json),
                React.createElement('button', {
                    key: 'clear-logs',
                    onClick: clearLogs,
                    style: { 
                        padding: '8px', 
                        borderRadius: '4px',
                        border: 'none',
                        backgroundColor: '#e74c3c',
                        color: 'white',
                        cursor: 'pointer',
                        transition: 'background-color 0.3s'
                    }
                }, translations.clear_logs)
            ]),
            React.createElement('div', { 
                key: 'statistics', 
                style: { 
                    marginBottom: '20px',
                    backgroundColor: theme === 'light' ? '#fff' : '#333',
                    padding: '15px',
                    borderRadius: '4px',
                    boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                } 
            }, [
                React.createElement('h3', { key: 'stats-title', style: { marginBottom: '10px' } }, translations.statistics),
                React.createElement('p', { key: 'total-logs' }, `${translations.total_logs}: ${statistics.totalLogs}`),
                React.createElement('p', { key: 'errors' }, `${translations.errors}: ${statistics.errorCount}`),
                React.createElement('p', { key: 'warnings' }, `${translations.warnings}: ${statistics.warningCount}`),
                React.createElement('p', { key: 'infos' }, `${translations.info_logs}: ${statistics.infoCount}`)
            ]),
            React.createElement('div', {
                key: 'log-list',
                ref: logListRef,
                style: { 
                    border: '1px solid #ddd', 
                    borderRadius: '4px', 
                    overflow: 'hidden',
                    transition: 'all 0.3s ease'
                }
            }, filteredLogs.slice(0, currentPage * logsPerPage).map((log, index) =>
                React.createElement('div', {
                    key: index,
                    style: {
                        padding: '10px',
                        borderBottom: '1px solid #eee',
                        display: 'flex',
                        alignItems: 'center',
                        backgroundColor: theme === 'light' ? '#fff' : '#333',
                        color: theme === 'light' ? '#333' : '#fff',
                        transition: 'background-color 0.3s ease'
                    }
                }, [
                    React.createElement('span', {
                        key: 'date',
                        style: { 
                            flexBasis: '180px', 
                            color: theme === 'light' ? '#7f8c8d' : '#bdc3c7'
                        }
                    }, log.date),
                    React.createElement('span', {
                        key: 'level',
                        style: {
                            flexBasis: '80px',
                            textAlign: 'center',
                            padding: '2px 6px',
                            borderRadius: '4px',
                            backgroundColor: log.level === 'error' ? '#e74c3c' : log.level === 'warning' ? '#f39c12' : '#3498db',
                            color: '#fff',
                            marginRight: '10px'
                        }
                    }, [getLogTypeIcon(log.level), ' ', log.level]),
                    React.createElement('span', { key: 'message', style: { flex: 1 } }, log.message)
                ])
            )),
            filteredLogs.length > currentPage * logsPerPage && React.createElement('button', {
                key: 'load-more',
                onClick: loadMoreLogs,
                style: { 
                    marginTop: '20px', 
                    padding: '10px',
                    width: '100%',
                    borderRadius: '4px',
                    border: 'none',
                    backgroundColor: '#3498db',
                    color: 'white',
                    cursor: 'pointer',
                    transition: 'background-color 0.3s'
                }
            }, translations.load_more)
        ]);
        }
    
        function getLogTypeIcon(level) {
            switch (level) {
                case 'error':
                    return '🔴';
                case 'warning':
                    return '🟠';
                case 'info':
                    return '🔵';
                default:
                    return '⚪';
            }
        }
    
        window.LogViewer = LogViewer;
    
    })(window, document, jQuery);