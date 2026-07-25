/**
 * Rhythm Theme Manager
 *
 * Three-state scheme: system → dark → light.
 * Resolved theme is always light|dark via documentElement data-theme.
 */

class RhythmThemeManager {
    /**
     * @param {string} storageKey
     */
    constructor(storageKey = 'rhythmColorScheme') {
        this.storageKey = storageKey;
        this.legacyStorageKey = 'rhythm-theme';
        this.scheme = this.getStoredScheme() ?? 'system';
        this.theme = this.resolveTheme(this.scheme);
        this.init();
    }

    /**
     * @returns {void}
     */
    init() {
        this.applyScheme(this.scheme, false);
        this.setupThemeToggle();
        this.setupSystemThemeListener();
        this.setupKeyboardShortcuts();
    }

    /**
     * @returns {string|null}
     */
    getStoredScheme() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            if (stored === 'system' || stored === 'light' || stored === 'dark') {
                return stored;
            }

            const legacy = localStorage.getItem(this.legacyStorageKey);
            if (legacy === 'light' || legacy === 'dark') {
                localStorage.setItem(this.storageKey, legacy);
                localStorage.removeItem(this.legacyStorageKey);

                return legacy;
            }
        } catch (e) {
            console.warn('Could not read theme from localStorage:', e);
        }

        return null;
    }

    /**
     * @param {string} scheme
     * @returns {void}
     */
    setStoredScheme(scheme) {
        try {
            localStorage.setItem(this.storageKey, scheme);
            localStorage.removeItem(this.legacyStorageKey);
        } catch (e) {
            console.warn('Could not save theme to localStorage:', e);
        }
    }

    /**
     * @returns {'light'|'dark'}
     */
    getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }

        return 'light';
    }

    /**
     * @param {string} scheme
     * @returns {'light'|'dark'}
     */
    resolveTheme(scheme) {
        if (scheme === 'system') {
            return this.getSystemTheme();
        }

        return scheme === 'dark' ? 'dark' : 'light';
    }

    /**
     * @param {string} scheme
     * @param {boolean} persist
     * @returns {void}
     */
    applyScheme(scheme, persist = true) {
        const previousTheme = this.theme;
        this.scheme = scheme;
        this.theme = this.resolveTheme(scheme);

        document.documentElement.setAttribute('data-theme', this.theme);
        document.documentElement.setAttribute('data-scheme', this.scheme);

        if (persist) {
            this.setStoredScheme(scheme);
        }

        this.updateThemeToggle();
        this.updateMetaThemeColor(this.theme);
        this.triggerThemeChangeEvent(previousTheme);
    }

    /**
     * @returns {void}
     */
    updateThemeToggle() {
        const toggle = document.querySelector('.rhythm-theme-toggle');
        if (!toggle) {
            return;
        }

        toggle.setAttribute('data-scheme', this.scheme);
        toggle.setAttribute('data-theme', this.theme);
        toggle.setAttribute('aria-label', `Theme: ${this.scheme}. Click to switch`);
        toggle.setAttribute('title', `Theme: ${this.scheme} (Ctrl+T)`);
    }

    /**
     * @param {'light'|'dark'} theme
     * @returns {void}
     */
    updateMetaThemeColor(theme) {
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }

        metaThemeColor.content = theme === 'dark' ? '#111827' : '#f3f4f6';
    }

    /**
     * @param {string} previousTheme
     * @returns {void}
     */
    triggerThemeChangeEvent(previousTheme) {
        document.dispatchEvent(new CustomEvent('rhythmThemeChange', {
            detail: {
                theme: this.theme,
                scheme: this.scheme,
                previousTheme: previousTheme,
            },
        }));
    }

    /**
     * @returns {void}
     */
    setupThemeToggle() {
        const toggle = document.querySelector('.rhythm-theme-toggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleScheme();
        });
        this.updateThemeToggle();
    }

    /**
     * @returns {void}
     */
    setupSystemThemeListener() {
        if (!window.matchMedia) {
            return;
        }

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => {
            if (this.scheme === 'system') {
                this.applyScheme('system', false);
            }
        };

        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleChange);
        } else {
            mediaQuery.addListener(handleChange);
        }
    }

    /**
     * @returns {void}
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 't') {
                e.preventDefault();
                this.toggleScheme();
            }
        });
    }

    /**
     * Cycle system → dark → light → system.
     *
     * @returns {void}
     */
    toggleScheme() {
        if (this.scheme === 'system') {
            this.applyScheme('dark');
        } else if (this.scheme === 'dark') {
            this.applyScheme('light');
        } else {
            this.applyScheme('system');
        }
    }

    /**
     * @deprecated Use toggleScheme()
     * @returns {void}
     */
    toggleTheme() {
        this.toggleScheme();
    }

    /**
     * @returns {'light'|'dark'}
     */
    getCurrentTheme() {
        return this.theme;
    }

    /**
     * @returns {string}
     */
    getCurrentScheme() {
        return this.scheme;
    }

    /**
     * @returns {boolean}
     */
    isDarkTheme() {
        return this.theme === 'dark';
    }

    /**
     * @returns {boolean}
     */
    isLightTheme() {
        return this.theme === 'light';
    }

    /**
     * @param {string} scheme
     * @returns {void}
     */
    forceScheme(scheme) {
        if (scheme !== 'system' && scheme !== 'light' && scheme !== 'dark') {
            return;
        }

        this.applyScheme(scheme);
    }

    /**
     * @deprecated Use forceScheme()
     * @param {string} theme
     * @returns {void}
     */
    forceTheme(theme) {
        this.forceScheme(theme === 'dark' ? 'dark' : 'light');
    }

    /**
     * @returns {void}
     */
    resetToSystemTheme() {
        this.applyScheme('system');
    }

    /**
     * @returns {void}
     */
    clearStoredTheme() {
        try {
            localStorage.removeItem(this.storageKey);
            localStorage.removeItem(this.legacyStorageKey);
        } catch (e) {
            console.warn('Could not clear theme from localStorage:', e);
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    window.rhythmThemeManager = new RhythmThemeManager();
});

window.RhythmThemeManager = RhythmThemeManager;
