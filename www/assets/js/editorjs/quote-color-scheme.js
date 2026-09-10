/**
 * QuoteColorScheme
 *
 * Extends the vendored @editorjs/quote `Quote` class with a `colorScheme`
 * field so editors can pick a color for the quote block's background.
 *
 * The available colors are NOT hardcoded here: they come from the active
 * theme's `theme.json` (`settings.color.palette`, mirroring WordPress's
 * theme.json convention), injected server-side as `window.THEATRECMS_COLOR_PALETTE`
 * and passed into this tool's `config.colorPalette` (see editorjs-config.js).
 * This keeps the picker's options a property of the active theme rather than
 * of core code, and matches what EditorJsHtmlConverter::renderQuote() resolves
 * server-side when rendering the saved `colorScheme` name.
 *
 * Usage:
 *   import QuoteColorScheme from './quote-color-scheme.js'; // or global script tag
 *
 *   const editor = new EditorJS({
 *     tools: {
 *       quote: {
 *         class: QuoteColorScheme,
 *         inlineToolbar: true,
 *         config: { colorPalette: window.THEATRECMS_COLOR_PALETTE || [] },
 *       }
 *     }
 *   });
 */

class QuoteColorScheme extends Quote {
    // Used only when a theme hasn't declared a settings.color.palette in its
    // theme.json, so the picker never has zero options.
    static get FALLBACK_COLOR_PALETTE() {
        return [{ name: 'grey', label: 'Grey', color: '#94a3b8' }];
    }

    static get sanitize() {
        return Object.assign({}, super.sanitize, { colorScheme: false });
    }

    constructor(params) {
        super(params);

        const configuredPalette = (params.config && params.config.colorPalette) || [];
        this.colorPalette = configuredPalette.length > 0
            ? configuredPalette
            : QuoteColorScheme.FALLBACK_COLOR_PALETTE;

        const requested = params.data && params.data.colorScheme;
        const isValid = this.colorPalette.some((preset) => preset.name === requested);
        this.data.colorScheme = isValid ? requested : this.colorPalette[0].name;

        this._wrapperEl = null;
    }

    render() {
        const el = super.render();
        this._wrapperEl = el;
        this._applyColorPreview();
        return el;
    }

    save(blockContent) {
        const data = super.save(blockContent);
        return Object.assign(data, { colorScheme: this.data.colorScheme });
    }

    renderSettings() {
        const alignmentItems = super.renderSettings();
        const colorItems = this.colorPalette.map((preset) => ({
            icon: this._swatchIcon(preset.color),
            label: this.api.i18n.t(`${preset.label} color`),
            onActivate: () => this._setColorScheme(preset.name),
            isActive: this.data.colorScheme === preset.name,
            closeOnActivate: false,
        }));

        return [...alignmentItems, { type: 'separator' }, ...colorItems];
    }

    _setColorScheme(name) {
        this.data.colorScheme = name;
        this._applyColorPreview();
        this.block.dispatchChange();
    }

    _applyColorPreview() {
        if (!this._wrapperEl) {
            return;
        }

        const preset = this.colorPalette.find((p) => p.name === this.data.colorScheme)
            || this.colorPalette[0];

        this._wrapperEl.style.background = preset.color;
        this._wrapperEl.style.boxShadow = 'none';
        this._wrapperEl.style.borderRadius = '8px';
        this._wrapperEl.style.padding = '1em 1.2em';
    }

    _swatchIcon(color) {
        return `<svg width="14" height="14" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg">
            <rect x="1" y="1" width="12" height="12" rx="3" fill="${color}" stroke="rgba(0,0,0,.18)"/>
        </svg>`;
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = QuoteColorScheme;
} else if (typeof define === 'function' && define.amd) {
    define([], () => QuoteColorScheme);
} else {
    window.QuoteColorScheme = QuoteColorScheme;
}
