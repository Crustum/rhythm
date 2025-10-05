/**
 * Rhythm Dashboard JavaScript
 *
 * Handles widget management, auto-refresh, and morphdom updates.
 */

class RhythmDashboard {
    constructor(config) {
        this.config = {
            refreshInterval: 5,
            baseUrl: '/rhythm/dashboard/',
            widgets: {},
            ...config
        };

        this.chartManager = new RhythmChartManager();
        this.widgetManager = new RhythmWidgetManager(this.config, this.chartManager);
        this.globalRefreshInterval = null;

        this.init();
    }

    /**
     * Initialize the dashboard
     */
    init() {
        this.setupMorphdom();
        this.widgetManager.registerWidgets();
        this.setupEventListeners();
        this.startAutoRefresh();
        this.setupThemeIntegration();
    }

    /**
     * Setup morphdom configuration
     */
    setupMorphdom() {
        this.morphdomOptions = {
            onBeforeElUpdated: (fromEl, toEl) => {
                if (fromEl.hasAttribute('data-vdom-ignore')) {
                    return false;
                }

                if (fromEl.tagName === 'INPUT' || fromEl.tagName === 'SELECT' || fromEl.tagName === 'TEXTAREA') {
                    if (fromEl === document.activeElement) {
                        toEl.focus();
                    }
                    if (fromEl.type !== 'file') {
                        toEl.value = fromEl.value;
                    }
                }

                if (fromEl.scrollTop > 0) {
                    toEl.scrollTop = fromEl.scrollTop;
                }

                return true;
            },
            onElUpdated: (el) => {
                this.triggerWidgetUpdate(el);

                if (el.classList.contains('widget-wrapper')) {
                    el.style.transition = 'all 0.3s ease';
                    el.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        el.style.transform = 'scale(1)';
                        setTimeout(() => {
                            el.style.transition = '';
                        }, 300);
                    }, 100);
                }
            },
            onNodeAdded: (node) => {
                if (node.nodeType === 1 && node.classList && node.classList.contains('widget-wrapper')) {
                    this.widgetManager.registerWidgetElement(node);
                }
            }
        };

        this.widgetManager.setMorphdomOptions(this.morphdomOptions);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const refreshAllBtn = document.getElementById('refresh-all');
        if (refreshAllBtn) {
            refreshAllBtn.addEventListener('click', () => {
                this.widgetManager.refreshDashboard();
            });
        }

        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('rhythm-sortable')) {
                this.widgetManager.handleSortableChange(e.target);
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('refresh-widget') ||
                e.target.closest('.refresh-widget')) {

                const button = e.target.classList.contains('refresh-widget') ?
                    e.target : e.target.closest('.refresh-widget');

                const widgetName = button.dataset.widget;
                if (widgetName) {
                    e.preventDefault();
                    this.widgetManager.refreshWidget(widgetName);
                }
            }
        });

        const periodSelector = document.getElementById('period-selector');
        if (periodSelector) {
            periodSelector.addEventListener('change', () => {
                this.widgetManager.refreshDashboard();
            });
        }

        const layoutSelector = document.getElementById('layout-selector');
        if (layoutSelector) {
            layoutSelector.addEventListener('change', (e) => {
                this.changeLayout(e.target.value);
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'r':
                        e.preventDefault();
                        this.widgetManager.refreshDashboard();
                        break;
                }
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAutoRefresh();
            } else {
                this.resumeAutoRefresh();
            }
        });
    }

    /**
     * Setup theme integration
     */
    setupThemeIntegration() {
        document.addEventListener('rhythmThemeChange', (e) => {
            this.handleThemeChange(e.detail.theme);
        });

        if (window.rhythmThemeManager) {
            this.handleThemeChange(window.rhythmThemeManager.getCurrentTheme());
        }
    }

    /**
     * Handle theme change
     */
    handleThemeChange(theme) {
        this.chartManager.updateChartsForTheme(theme);

        setTimeout(() => {
            this.widgetManager.getWidgets().forEach((widget, widgetName) => {
                if (widget.element) {
                    this.chartManager.renderCharts(widget.element);
                }
            });
        }, 100);
    }

    /**
     * Start auto-refresh for the dashboard
     */
    startAutoRefresh() {
        this.globalRefreshInterval = setInterval(() => {
            if (!document.hidden && !this.widgetManager.isRefreshing()) {
                this.widgetManager.refreshDashboard();
            }
        }, this.config.refreshInterval * 1000);
    }

    /**
     * Pause all auto-refresh
     */
    pauseAutoRefresh() {
        if (this.globalRefreshInterval) {
            clearInterval(this.globalRefreshInterval);
            this.globalRefreshInterval = null;
        }

        this.widgetManager.pauseAutoRefresh();
    }

    /**
     * Resume all auto-refresh
     */
    resumeAutoRefresh() {
        this.startAutoRefresh();
        this.widgetManager.resumeAutoRefresh();
    }

    /**
     * Change the dashboard layout
     */
    changeLayout(layoutName) {
        const url = new URL(window.location);
        url.searchParams.set('layout', layoutName);

        const currentPeriod = url.searchParams.get('period');
        if (currentPeriod) {
            url.searchParams.set('period', currentPeriod);
        }

        const currentSort = url.searchParams.get('sort');
        if (currentSort) {
            url.searchParams.set('sort', currentSort);
        }

        window.location.href = url.toString();
    }

    /**
     * Trigger widget update event
     */
    triggerWidgetUpdate(element) {
        const event = new CustomEvent('widgetUpdated', {
            detail: { element },
            bubbles: true
        });
        element.dispatchEvent(event);
    }

    /**
     * Destroy the dashboard instance
     */
    destroy() {
        if (this.globalRefreshInterval) {
            clearInterval(this.globalRefreshInterval);
        }

        this.chartManager.destroy();
        this.widgetManager.destroy();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.rhythmConfig !== 'undefined') {
        window.dashboard = new RhythmDashboard(window.rhythmConfig);
        setTimeout(() => {
            if (window.dashboard && window.rhythmThemeManager) {
                window.dashboard.chartManager.updateChartsForTheme(window.rhythmThemeManager.getCurrentTheme());
            }
        }, 0);
    }
});

window.RhythmDashboard = RhythmDashboard;
