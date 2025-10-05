/**
 * Rhythm Theme Manager
 *
 * Handles theme switching, browser theme detection, and theme persistence.
 */

class RhythmThemeManager {
    constructor() {
        this.theme = this.getStoredTheme() || this.getSystemTheme();
        this.init();
    }

    /**
     * Initialize the theme manager
     */
    init() {
        this.applyTheme(this.theme);
        this.setupThemeToggle();
        this.setupSystemThemeListener();
        this.setupKeyboardShortcuts();
    }

    /**
     * Get stored theme from localStorage
     */
    getStoredTheme() {
        try {
            return localStorage.getItem('rhythm-theme');
        } catch (e) {
            console.warn('Could not read theme from localStorage:', e);
            return null;
        }
    }

    /**
     * Store theme in localStorage
     */
    setStoredTheme(theme) {
        try {
            localStorage.setItem('rhythm-theme', theme);
        } catch (e) {
            console.warn('Could not save theme to localStorage:', e);
        }
    }

    /**
     * Get system theme preference
     */
    getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    /**
     * Apply theme to the document
     */
    applyTheme(theme) {
        const root = document.documentElement;
        root.setAttribute('data-theme', theme);
        this.updateThemeToggle(theme);
        this.setStoredTheme(theme);
        this.updateMetaThemeColor(theme);
        this.triggerThemeChangeEvent(theme);
    }

    /**
     * Update theme toggle button state
     */
    updateThemeToggle(theme) {
        const toggle = document.querySelector('.rhythm-theme-toggle');
        if (toggle) {
            toggle.setAttribute('data-theme', theme);
            toggle.setAttribute('aria-label', `Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`);
        }
    }

    /**
     * Update meta theme-color for mobile browsers
     */
    updateMetaThemeColor(theme) {
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        if (theme === 'dark') {
            metaThemeColor.content = '#0f172a';
        } else {
            metaThemeColor.content = '#3b82f6';
        }
    }

    /**
     * Trigger theme change event
     */
    triggerThemeChangeEvent(theme) {
        const event = new CustomEvent('rhythmThemeChange', {
            detail: { theme, previousTheme: this.theme }
        });
        document.dispatchEvent(event);
    }

    /**
     * Setup theme toggle button
     */
    setupThemeToggle() {
        const toggle = document.querySelector('.rhythm-theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                this.toggleTheme();
            });
            this.updateThemeToggle(this.theme);
        }
    }

    /**
     * Setup system theme change listener
     */
    setupSystemThemeListener() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            const handleChange = (e) => {
                const storedTheme = this.getStoredTheme();
                if (!storedTheme) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);
                }
            };
            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', handleChange);
            } else {
                mediaQuery.addListener(handleChange);
            }
        }
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 't') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    /**
     * Toggle between light and dark themes
     */
    toggleTheme() {
        const newTheme = this.theme === 'dark' ? 'light' : 'dark';
        this.theme = newTheme;
        this.applyTheme(newTheme);
        this.showThemeChangeFeedback(newTheme);
    }

    /**
     * Show visual feedback for theme change
     */
    showThemeChangeFeedback(theme) {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: ${theme === 'dark' ? '#0f172a' : '#ffffff'};
            opacity: 0;
            z-index: 9999;
            pointer-events: none;
            transition: opacity 0.3s ease;
        `;
        document.body.appendChild(overlay);
        requestAnimationFrame(() => {
            overlay.style.opacity = '0.1';
            setTimeout(() => {
                overlay.style.opacity = '0';
                setTimeout(() => {
                    document.body.removeChild(overlay);
                }, 300);
            }, 150);
        });
    }

    /**
     * Get current theme
     */
    getCurrentTheme() {
        return this.theme;
    }

    /**
     * Check if dark theme is active
     */
    isDarkTheme() {
        return this.theme === 'dark';
    }

    /**
     * Check if light theme is active
     */
    isLightTheme() {
        return this.theme === 'light';
    }

    /**
     * Force apply a specific theme (for testing)
     */
    forceTheme(theme) {
        this.theme = theme;
        this.applyTheme(theme);
    }

    /**
     * Reset to system theme
     */
    resetToSystemTheme() {
        const systemTheme = this.getSystemTheme();
        this.theme = systemTheme;
        this.applyTheme(systemTheme);
        this.setStoredTheme('');
    }

    /**
     * Clear stored theme preference (for debugging)
     */
    clearStoredTheme() {
        try {
            localStorage.removeItem('rhythm-theme');
        } catch (e) {
            console.warn('Could not clear theme from localStorage:', e);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    window.rhythmThemeManager = new RhythmThemeManager();
});

window.RhythmThemeManager = RhythmThemeManager;
