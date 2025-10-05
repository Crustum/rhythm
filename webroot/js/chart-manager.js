/**
 * Rhythm Chart Manager
 *
 * Handles Chart.js initialization, updates, and theme integration.
 */

class RhythmChartManager {
    constructor() {
        this.charts = new Map();
    }

    /**
     * Get current theme from theme manager or detect from DOM
     */
    getCurrentTheme() {
        if (window.rhythmThemeManager) {
            return window.rhythmThemeManager.getCurrentTheme();
        }

        const root = document.documentElement;
        const dataTheme = root.getAttribute('data-theme');
        if (dataTheme) {
            return dataTheme;
        }

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    /**
     * Update charts for new theme
     */
    updateChartsForTheme(theme) {
        this.charts.forEach((chart, chartId) => {
            if (chart && chart.options) {
                const isDark = theme === 'dark';

                if (chart.options.scales) {
                    if (chart.options.scales.x && chart.options.scales.x.grid) {
                        chart.options.scales.x.grid.color = isDark ? '#334155' : '#f3f4f6';
                    }
                    if (chart.options.scales.y && chart.options.scales.y.grid) {
                        chart.options.scales.y.grid.color = isDark ? '#334155' : '#f3f4f6';
                    }
                }

                if (chart.options.plugins && chart.options.plugins.tooltip) {
                    chart.options.plugins.tooltip.backgroundColor = isDark ? '#1e293b' : '#ffffff';
                    chart.options.plugins.tooltip.titleColor = isDark ? '#f8fafc' : '#111827';
                    chart.options.plugins.tooltip.bodyColor = isDark ? '#cbd5e1' : '#6b7280';
                    chart.options.plugins.tooltip.borderColor = isDark ? '#334155' : '#e5e7eb';
                }

                chart.update('none');
            }
        });
    }

    /**
     * Render or update charts within a widget
     */
    renderCharts(widgetEl) {
        setTimeout(() => {
            const chartScript = widgetEl.querySelector('script[data-rhythm-charts]');
            if (!chartScript) {
                return;
            }

            try {
                const chartConfigs = JSON.parse(chartScript.textContent);
                const widgetName = widgetEl.dataset.widget || widgetEl.closest('[data-widget]')?.dataset.widget;

                if (!Array.isArray(chartConfigs)) {
                    console.error('Chart configuration is not an array for widget:', widgetName);
                    return;
                }

                chartConfigs.forEach(config => {
                    const canvas = widgetEl.querySelector(`canvas[data-chart-name="${config.name}"]`);
                    if (!canvas) {
                        console.warn(`Canvas for chart "${config.name}" not found in widget "${widgetName}".`);
                        return;
                    }

                    const chartId = `${widgetName}-${config.name}`;

                    if (this.charts.has(chartId)) {
                        const existingChart = this.charts.get(chartId);
                        if (this.updateExistingChart(existingChart, config)) {
                            return;
                        }
                    }

                    this.initChart(chartId, canvas, config);
                });

            } catch (e) {
                console.error('Error rendering charts:', e, 'Script content:', chartScript.textContent);
            }
        }, 50);
    }

    /**
     * Update existing chart with new data without full rerender
     */
    updateExistingChart(chart, config) {
        try {
            if (!chart || !config || !config.data) {
                return false;
            }

            let labels = [];
            let datasets = [];
            const unit = config.options?.unit || '';

            const isMultiSeries = config.data &&
                typeof config.data === 'object' &&
                !Array.isArray(config.data) &&
                Object.keys(config.data).length > 0 &&
                Object.values(config.data).every(value =>
                    typeof value === 'object' &&
                    value !== null &&
                    !Array.isArray(value)
                );

            if (isMultiSeries) {
                const allLabels = new Set();
                Object.values(config.data).forEach(series => {
                    Object.keys(series).forEach(label => allLabels.add(label));
                });
                labels = Array.from(allLabels).sort();

                datasets = Object.entries(config.data).map(([seriesName, seriesData], idx) => {
                    let color = config.options?.colors?.[idx] || config.options?.borderColor || '#4A5568';
                    const data = labels.map(label => seriesData[label] ?? null);

                    return {
                        label: seriesName,
                        data: data,
                        borderColor: color,
                        borderWidth: 2,
                        borderCapStyle: 'round',
                        pointHitRadius: 10,
                        pointStyle: false,
                        tension: 0.2,
                        spanGaps: false,
                        ...(config.options?.dataset || {})
                    };
                });
            } else {
                labels = Object.keys(config.data);
                datasets = [{
                    label: config.options?.label || 'Dataset',
                    data: Object.values(config.data),
                    borderColor: config.options?.borderColor || '#4A5568',
                    borderWidth: 2,
                    borderCapStyle: 'round',
                    pointHitRadius: 10,
                    pointStyle: false,
                    tension: 0.2,
                    spanGaps: false,
                    ...(config.options?.dataset || {})
                }];
            }

            chart.data.labels = labels;
            chart.data.datasets = datasets;

            chart.update('none');

            return true;
        } catch (e) {
            console.error('Error updating existing chart:', e);
            return false;
        }
    }

    /**
     * Initialize a single chart instance
     */
    initChart(chartId, canvas, config) {
        if (this.charts.has(chartId)) {
            this.charts.get(chartId).destroy();
        }

        if (!canvas || !config || !config.data) {
            console.warn(`Chart initialization skipped for ${chartId}: canvas or config/data missing.`);
            return;
        }

        let labels = [];
        let datasets = [];
        const unit = config.options?.unit || '';

        const isMultiSeries = config.data &&
            typeof config.data === 'object' &&
            !Array.isArray(config.data) &&
            Object.keys(config.data).length > 0 &&
            Object.values(config.data).every(value =>
                typeof value === 'object' &&
                value !== null &&
                !Array.isArray(value)
            );

        if (isMultiSeries) {
            const allLabels = new Set();
            Object.values(config.data).forEach(series => {
                Object.keys(series).forEach(label => allLabels.add(label));
            });
            labels = Array.from(allLabels).sort();

            datasets = Object.entries(config.data).map(([seriesName, seriesData], idx) => {
                let color = config.options?.colors?.[idx] || config.options?.borderColor || '#4A5568';
                const data = labels.map(label => seriesData[label] ?? null);

                return {
                    label: seriesName,
                    data: data,
                    borderColor: color,
                    borderWidth: 2,
                    borderCapStyle: 'round',
                    pointHitRadius: 10,
                    pointStyle: false,
                    tension: 0.2,
                    spanGaps: false,
                    ...(config.options?.dataset || {})
                };
            });
        } else {
            labels = Object.keys(config.data);
            datasets = [{
                label: config.options?.label || 'Dataset',
                data: Object.values(config.data),
                borderColor: config.options?.borderColor || '#4A5568',
                borderWidth: 2,
                borderCapStyle: 'round',
                pointHitRadius: 10,
                pointStyle: false,
                tension: 0.2,
                spanGaps: false,
                ...(config.options?.dataset || {})
            }];
        }

        const currentTheme = this.getCurrentTheme();
        const isDark = currentTheme === 'dark';

        const themeTooltipColors = {
            backgroundColor: isDark ? '#1e293b' : '#ffffff',
            titleColor: isDark ? '#f8fafc' : '#111827',
            bodyColor: isDark ? '#cbd5e1' : '#6b7280',
            borderColor: isDark ? '#334155' : '#e5e7eb',
        };

        const chartJsOptions = config.options?.chartJs || {};
        const mergedChartJsOptions = {
            ...chartJsOptions,
            plugins: {
                ...chartJsOptions.plugins,
                tooltip: {
                    ...chartJsOptions.plugins?.tooltip,
                    ...themeTooltipColors,
                }
            }
        };

        const chart = new Chart(canvas, {
            type: config.type || 'line',
            data: {
                labels: labels,
                datasets: datasets,
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    autoPadding: false,
                },
                scales: {
                    x: {
                        display: false,
                        grid: {
                            display: false,
                        },
                    },
                    y: {
                        display: false,
                        min: 0,
                        grid: {
                            display: false,
                        },
                    },
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                },
                ...mergedChartJsOptions
            }
        });

        this.charts.set(chartId, chart);
    }

    /**
     * Destroy all charts
     */
    destroy() {
        this.charts.forEach((chart) => {
            if (chart) {
                chart.destroy();
            }
        });
        this.charts.clear();
    }
}

window.RhythmChartManager = RhythmChartManager;
