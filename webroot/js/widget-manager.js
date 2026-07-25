/**
 * Rhythm Widget Manager
 *
 * Handles widget registration, refresh intervals, and widget operations.
 */

class RhythmWidgetManager {
    constructor(config, chartManager) {
        this.config = config;
        this.chartManager = chartManager;
        this.widgets = new Map();
        this.refreshIntervals = new Map();
        this.isRefreshing = false;
    }

    /**
     * Register all widgets on the page
     */
    registerWidgets() {
        const widgetContainers = document.querySelectorAll('.widget-container[data-widget]');

        widgetContainers.forEach(container => {
            this.registerWidgetElement(container);
        });
    }

    /**
     * Register a single widget element
     */
    registerWidgetElement(element) {
        const widgetName = element.dataset.widget;
        if (!widgetName) return;

        const config = this.config.widgets[widgetName] || {};

        this.widgets.set(widgetName, {
            element,
            config,
            lastUpdate: Date.now(),
            isRefreshing: false
        });

        const refreshInterval = config.refreshInterval || this.getDefaultRefreshInterval(widgetName);
        if (refreshInterval) {
            this.startWidgetRefresh(widgetName, refreshInterval);
        }

        this.chartManager.renderCharts(element);
    }

    /**
     * Get default refresh interval for widget type
     */
    getDefaultRefreshInterval(widgetName) {
        const intervals = {
            'server-state': 30,
            'usage': 30,
            'slow-queries': 60,
            'exceptions': 60,
            'cache': 60,
            'queues': 30
        };

        return intervals[widgetName] || 60;
    }

    /**
     * Refresh a specific widget
     */
    async refreshWidget(widgetName) {
        const widget = this.widgets.get(widgetName);
        if (!widget || widget.isRefreshing) {
            return;
        }

        widget.isRefreshing = true;
        this.showWidgetLoading(widgetName);

        try {
            const period = document.getElementById('period-selector')?.value || '60';
            const encodedWidgetName = encodeURIComponent(widgetName);
            const url = new URL(`${this.config.baseUrl}widget/${encodedWidgetName}`, window.location.origin);
            url.searchParams.append('period', period);

            const currentSort = new URLSearchParams(window.location.search).get('sort');
            if (currentSort) {
                url.searchParams.append('sort', currentSort);
            }

            const currentLayout = new URLSearchParams(window.location.search).get('layout');
            if (currentLayout) {
                url.searchParams.append('layout', currentLayout);
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const html = await response.text();
            await this.updateWidgetContent(widgetName, html);

            widget.lastUpdate = Date.now();

        } catch (error) {
            console.error(`Error refreshing widget ${widgetName}:`, error);
            this.showWidgetError(widgetName, error.message);
        } finally {
            widget.isRefreshing = false;
            this.hideWidgetLoading(widgetName);
        }
    }

    /**
     * Refresh the entire dashboard
     */
    async refreshDashboard() {
        if (this.isRefreshing) {
            return;
        }

        this.isRefreshing = true;
        this.showGlobalLoading();

        try {
            const period = document.getElementById('period-selector')?.value || '60';
            const url = new URL(`${this.config.baseUrl}refresh`, window.location.origin);
            url.searchParams.append('period', period);

            const currentLayout = new URLSearchParams(window.location.search).get('layout');
            if (currentLayout) {
                url.searchParams.append('layout', currentLayout);
            }

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const html = await response.text();
            await this.updateDashboardContent(html);

            this.updateLastUpdatedTime();

        } catch (error) {
            console.error('Error refreshing dashboard:', error);
            this.showGlobalError(error.message);
        } finally {
            this.isRefreshing = false;
            this.hideGlobalLoading();
        }
    }

    /**
     * Update widget content using morphdom
     */
    async updateWidgetContent(widgetName, html) {
        const widget = this.widgets.get(widgetName);
        if (!widget || !widget.element) {
            return;
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newWidgetWrapper = doc.querySelector('.widget-wrapper');

        if (newWidgetWrapper && window.morphdom) {
            const existingWidgetWrapper = widget.element.querySelector('.widget-wrapper');
            if (existingWidgetWrapper) {
                morphdom(existingWidgetWrapper, newWidgetWrapper, this.morphdomOptions);
            } else {
                widget.element.innerHTML = newWidgetWrapper.outerHTML;
            }
        } else if (newWidgetWrapper) {
            const existingWidgetWrapper = widget.element.querySelector('.widget-wrapper');
            if (existingWidgetWrapper) {
                existingWidgetWrapper.outerHTML = newWidgetWrapper.outerHTML;
            } else {
                widget.element.innerHTML = newWidgetWrapper.outerHTML;
            }
        }
        this.chartManager.renderCharts(widget.element);
    }

    /**
     * Update dashboard content using morphdom
     */
    async updateDashboardContent(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newDashboard = doc.querySelector('.dashboard-grid');
        const currentDashboard = document.querySelector('.dashboard-grid');

        if (newDashboard && currentDashboard && window.morphdom) {
            morphdom(currentDashboard, newDashboard, this.morphdomOptions);
            this.registerWidgets();
        } else if (newDashboard && currentDashboard) {
            currentDashboard.innerHTML = newDashboard.innerHTML;
            this.registerWidgets();
        }
    }

    /**
     * Start auto-refresh for a specific widget
     */
    startWidgetRefresh(widgetName, interval) {
        this.stopWidgetRefresh(widgetName);

        const intervalId = setInterval(() => {
            if (!document.hidden && !this.isRefreshing) {
                this.refreshWidget(widgetName);
            }
        }, interval * 1000);

        this.refreshIntervals.set(widgetName, intervalId);
    }

    /**
     * Stop auto-refresh for a specific widget
     */
    stopWidgetRefresh(widgetName) {
        const intervalId = this.refreshIntervals.get(widgetName);
        if (intervalId) {
            clearInterval(intervalId);
            this.refreshIntervals.delete(widgetName);
        }
    }

    /**
     * Pause all auto-refresh
     */
    pauseAutoRefresh() {
        this.refreshIntervals.forEach((intervalId) => {
            clearInterval(intervalId);
        });
        this.refreshIntervals.clear();
    }

    /**
     * Resume all auto-refresh
     */
    resumeAutoRefresh() {
        this.widgets.forEach((widget, widgetName) => {
            const refreshInterval = widget.config.refreshInterval || this.getDefaultRefreshInterval(widgetName);
            this.startWidgetRefresh(widgetName, refreshInterval);
        });
    }

    /**
     * Show loading state for a widget
     */
    showWidgetLoading(widgetName) {
        const widget = this.widgets.get(widgetName);
        if (!widget || !widget.element) return;

        const refreshBtn = widget.element.querySelector('.refresh-widget');
        if (refreshBtn) {
            const originalContent = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            refreshBtn.disabled = true;
            refreshBtn.dataset.originalContent = originalContent;
        }
    }

    /**
     * Hide loading state for a widget
     */
    hideWidgetLoading(widgetName) {
        const widget = this.widgets.get(widgetName);
        if (!widget || !widget.element) return;

        const refreshBtn = widget.element.querySelector('.refresh-widget');
        if (refreshBtn && refreshBtn.dataset.originalContent) {
            refreshBtn.innerHTML = refreshBtn.dataset.originalContent;
            refreshBtn.disabled = false;
            delete refreshBtn.dataset.originalContent;
        }
    }

    /**
     * Show error state for a widget
     */
    showWidgetError(widgetName, errorMessage) {
        const widget = this.widgets.get(widgetName);
        if (!widget || !widget.element) return;

        const content = widget.element.querySelector('.widget-content');
        if (content) {
            content.innerHTML = `
                <div class="widget-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error loading widget</p>
                    <small>${this.escapeHtml(errorMessage)}</small>
                </div>
            `;
        }
    }

    /**
     * Show global loading state
     */
    showGlobalLoading() {
        const refreshBtn = document.getElementById('refresh-all');
        if (refreshBtn) {
            const originalContent = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
            refreshBtn.dataset.originalContent = originalContent;
        }
    }

    /**
     * Hide global loading state
     */
    hideGlobalLoading() {
        const refreshBtn = document.getElementById('refresh-all');
        if (refreshBtn && refreshBtn.dataset.originalContent) {
            refreshBtn.innerHTML = refreshBtn.dataset.originalContent;
            refreshBtn.disabled = false;
            delete refreshBtn.dataset.originalContent;
        }
    }

    /**
     * Show global error
     */
    showGlobalError(errorMessage) {
        let errorDiv = document.getElementById('global-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'global-error';
            errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fed7d7;
                color: #742a2a;
                padding: 1rem;
                border-radius: 4px;
                border-left: 4px solid #f56565;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                z-index: 1000;
                max-width: 300px;
            `;
            document.body.appendChild(errorDiv);
        }

        errorDiv.innerHTML = `
            <strong>Error:</strong> ${this.escapeHtml(errorMessage)}
            <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; color: #742a2a; cursor: pointer; font-size: 1.2rem;">&times;</button>
        `;

        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 5000);
    }

    /**
     * Update last updated time
     */
    updateLastUpdatedTime() {
        const lastUpdatedEl = document.getElementById('last-updated');
        if (lastUpdatedEl) {
            lastUpdatedEl.textContent = new Date().toLocaleTimeString();
        }
    }

    /**
     * Handle generic sortable change for any widget
     */
    handleSortableChange(select) {
        const widget = select.dataset.widget;
        const value = select.value;
        const currentValue = select.dataset.current;

        if (value === currentValue) {
            return;
        }

        const url = new URL(window.location);
        url.searchParams.set('sort', value);

        const currentPeriod = url.searchParams.get('period');
        if (currentPeriod) {
            url.searchParams.set('period', currentPeriod);
        }

        const currentLayout = url.searchParams.get('layout');
        if (currentLayout) {
            url.searchParams.set('layout', currentLayout);
        }

        if (window.history && window.history.pushState) {
            window.history.pushState({}, '', url.toString());
        }

        if (widget && this.widgets.has(widget)) {
            this.refreshWidget(widget);
        } else {
            console.warn(`Widget ${widget} not found for sorting`);
        }

        select.dataset.current = value;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Set morphdom options
     */
    setMorphdomOptions(options) {
        this.morphdomOptions = options;
    }

    /**
     * Get widgets map
     */
    getWidgets() {
        return this.widgets;
    }

    /**
     * Check if refreshing
     */
    isRefreshing() {
        return this.isRefreshing;
    }

    /**
     * Destroy the widget manager
     */
    destroy() {
        this.refreshIntervals.forEach((intervalId) => {
            clearInterval(intervalId);
        });

        this.widgets.clear();
        this.refreshIntervals.clear();
    }
}

window.RhythmWidgetManager = RhythmWidgetManager;
