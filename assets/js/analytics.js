jQuery(document).ready(function($) {
    'use strict';
    
    let charts = {};
    let currentPeriod = '30';
    
    // Initialize analytics
    init();
    
    function init() {
        bindEvents();
        loadAnalyticsData();
    }
    
    function bindEvents() {
        // Period selector change
        $('#analytics-period').on('change', function() {
            currentPeriod = $(this).val();
            loadAnalyticsData();
        });
        
        // Export buttons
        $('.cmp-export-controls button').on('click', function() {
            const reportType = $(this).data('report');
            exportReport(reportType);
        });
    }
    
    function loadAnalyticsData() {
        showLoading();
        
        $.ajax({
            url: cmp_analytics.ajax_url,
            type: 'POST',
            data: {
                action: 'cmp_get_analytics_data',
                nonce: cmp_analytics.nonce,
                period: currentPeriod
            },
            success: function(response) {
                if (response.success) {
                    renderAnalytics(response.data);
                    hideLoading();
                } else {
                    showError(response.data || cmp_analytics.strings.error);
                }
            },
            error: function() {
                showError(cmp_analytics.strings.error);
            }
        });
    }
    
    function renderAnalytics(data) {
        renderOverviewStats(data.overview);
        renderCaseTrendsChart(data.case_trends);
        renderStatusDistributionChart(data.status_distribution);
        renderResolutionTimeChart(data.resolution_times);
        renderFileTypeChart(data.file_statistics);
        renderUserPerformanceTable(data.user_performance);
        renderResolutionAnalysis(data.resolution_times);
        renderFileStatistics(data.file_statistics);
    }
    
    function renderOverviewStats(stats) {
        const container = $('#overview-stats');
        container.empty();
        
        const statCards = [
            { title: 'Total Cases', value: stats.total_cases, color: '#0073aa' },
            { title: 'Pending Cases', value: stats.pending_cases, color: '#f39c12' },
            { title: 'In Progress', value: stats.in_progress_cases, color: '#3498db' },
            { title: 'Completed', value: stats.completed_cases, color: '#27ae60' },
            { title: 'Rejected', value: stats.rejected_cases, color: '#e74c3c' },
            { title: 'Total Files', value: stats.total_files, color: '#9b59b6' },
            { title: 'File Size', value: formatFileSize(stats.total_file_size), color: '#34495e' },
            { title: 'Comments', value: stats.total_comments, color: '#16a085' },
            { title: 'Avg Resolution', value: stats.avg_resolution_time + 'h', color: '#e67e22' },
            { title: 'Completion Rate', value: stats.completion_rate + '%', color: '#2ecc71' }
        ];
        
        statCards.forEach(function(stat) {
            const card = $(`
                <div class="cmp-stat-card" style="border-left-color: ${stat.color}">
                    <h3 style="color: ${stat.color}">${stat.value}</h3>
                    <p>${stat.title}</p>
                </div>
            `);
            container.append(card);
        });
    }
    
    function renderCaseTrendsChart(trends) {
        const ctx = document.getElementById('case-trends-chart').getContext('2d');
        
        if (charts.trends) {
            charts.trends.destroy();
        }
        
        const labels = trends.map(item => formatDate(item.date));
        const totalData = trends.map(item => item.total_cases);
        const completedData = trends.map(item => item.completed_cases);
        const rejectedData = trends.map(item => item.rejected_cases);
        
        charts.trends = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Cases',
                        data: totalData,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Completed',
                        data: completedData,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Rejected',
                        data: rejectedData,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
    
    function renderStatusDistributionChart(distribution) {
        const ctx = document.getElementById('status-distribution-chart').getContext('2d');
        
        if (charts.status) {
            charts.status.destroy();
        }
        
        const labels = distribution.map(item => capitalizeFirst(item.status));
        const data = distribution.map(item => item.count);
        const colors = ['#f39c12', '#3498db', '#27ae60', '#e74c3c', '#9b59b6'];
        
        charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    function renderResolutionTimeChart(resolutionData) {
        const ctx = document.getElementById('resolution-time-chart').getContext('2d');
        
        if (charts.resolution) {
            charts.resolution.destroy();
        }
        
        const labels = Object.keys(resolutionData.distribution);
        const data = Object.values(resolutionData.distribution);
        
        charts.resolution = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of Cases',
                    data: data,
                    backgroundColor: '#0073aa',
                    borderColor: '#005a87',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    function renderFileTypeChart(fileStats) {
        const ctx = document.getElementById('file-type-chart').getContext('2d');
        
        if (charts.fileType) {
            charts.fileType.destroy();
        }
        
        if (!fileStats.file_types || fileStats.file_types.length === 0) {
            ctx.canvas.style.display = 'none';
            return;
        }
        
        ctx.canvas.style.display = 'block';
        
        const labels = fileStats.file_types.map(item => item.file_type || 'Unknown');
        const data = fileStats.file_types.map(item => parseInt(item.count));
        const colors = ['#e74c3c', '#3498db', '#27ae60', '#f39c12', '#9b59b6', '#34495e', '#16a085'];
        
        charts.fileType = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    function renderUserPerformanceTable(performance) {
        const tbody = $('#user-performance-table tbody');
        tbody.empty();
        
        if (!performance || performance.length === 0) {
            tbody.append('<tr><td colspan="6">No data available</td></tr>');
            return;
        }
        
        performance.forEach(function(user) {
            const row = $(`
                <tr>
                    <td>${user.display_name}</td>
                    <td>${user.total_cases}</td>
                    <td>${user.completed_cases}</td>
                    <td>${user.rejected_cases}</td>
                    <td>${user.completion_rate}%</td>
                    <td>${user.avg_resolution_time}h</td>
                </tr>
            `);
            tbody.append(row);
        });
    }
    
    function renderResolutionAnalysis(resolutionData) {
        const container = $('#resolution-analysis');
        container.empty();
        
        const metrics = [
            { title: 'Minimum Time', value: resolutionData.min + 'h' },
            { title: 'Maximum Time', value: resolutionData.max + 'h' },
            { title: 'Average Time', value: resolutionData.avg + 'h' },
            { title: 'Median Time', value: resolutionData.median + 'h' }
        ];
        
        metrics.forEach(function(metric) {
            const card = $(`
                <div class="cmp-resolution-metric">
                    <h4>${metric.title}</h4>
                    <div class="value">${metric.value}</div>
                </div>
            `);
            container.append(card);
        });
    }
    
    function renderFileStatistics(fileStats) {
        const container = $('#file-statistics');
        container.empty();
        
        // File types section
        if (fileStats.file_types && fileStats.file_types.length > 0) {
            const fileTypesSection = $(`
                <div class="cmp-file-stat-section">
                    <h4>File Types</h4>
                </div>
            `);
            
            fileStats.file_types.forEach(function(type) {
                const item = $(`
                    <div class="cmp-file-stat-item">
                        <span>${type.file_type || 'Unknown'}</span>
                        <span>${type.count} files (${formatFileSize(type.total_size)})</span>
                    </div>
                `);
                fileTypesSection.append(item);
            });
            
            container.append(fileTypesSection);
        }
        
        // Cloud providers section
        if (fileStats.cloud_providers && fileStats.cloud_providers.length > 0) {
            const providersSection = $(`
                <div class="cmp-file-stat-section">
                    <h4>Cloud Providers</h4>
                </div>
            `);
            
            fileStats.cloud_providers.forEach(function(provider) {
                const item = $(`
                    <div class="cmp-file-stat-item">
                        <span>${capitalizeFirst(provider.cloud_provider)}</span>
                        <span>${provider.count} files (${formatFileSize(provider.total_size)})</span>
                    </div>
                `);
                providersSection.append(item);
            });
            
            container.append(providersSection);
        }
    }
    
    function exportReport(reportType) {
        const button = $(`[data-report="${reportType}"]`);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: cmp_analytics.ajax_url,
            type: 'POST',
            data: {
                action: 'cmp_export_report',
                nonce: cmp_analytics.nonce,
                format: 'csv',
                period: currentPeriod,
                report_type: reportType
            },
            success: function(response) {
                if (response.success) {
                    downloadCSV(response.data.csv_data, response.data.filename);
                    showNotice(cmp_analytics.strings.export_success, 'success');
                } else {
                    showNotice(response.data || cmp_analytics.strings.export_error, 'error');
                }
            },
            error: function() {
                showNotice(cmp_analytics.strings.export_error, 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function downloadCSV(csvData, filename) {
        const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    function showLoading() {
        $('#analytics-loading').show();
        $('#analytics-content').hide();
    }
    
    function hideLoading() {
        $('#analytics-loading').hide();
        $('#analytics-content').show();
    }
    
    function showError(message) {
        hideLoading();
        showNotice(message, 'error');
    }
    
    function showNotice(message, type) {
        const notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap').prepend(notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
        
        // Manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        });
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
    }
}); 