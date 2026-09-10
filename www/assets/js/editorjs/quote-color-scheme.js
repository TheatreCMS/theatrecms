/**
 * QuoteColorScheme
 *
 * Extends the vendored @editorjs/quote `Quote` class with a `colorScheme`
 * field so editors can pick a preset background for the quote block.
 *
 * Mirrors Ghost's kg-callout-card-* classes (see
 * EditorJsHtmlConverter::QUOTE_COLOR_SCHEME_PRESETS) so the frontend
 * renderer can emit markup that reuses the theme's existing cards CSS. The
 * admin app doesn't load that compiled theme CSS, so the swatches/preview
 * below are hand-picked rgba approximations for in-editor use only (same
 * approach already used by cta-card.js's BACKGROUND_PRESETS).
 *
 * Usage:
 *   import QuoteColorScheme from './quote-color-scheme.js'; // or global script tag
 *
 *   const editor = new EditorJS({
 *     tools: {
 *       quote: {
 *         class: QuoteColorScheme,
 *         inlineToolbar: true,
 *       }
 *     }
 *   });
 */

class QuoteColorScheme extends Quote {
    static get COLOR_SCHEME_PRESETS() {
        return [
            { key: 'grey', label: 'Grey', swatch: 'rgba(124,139,154,.35)' },
            { key: 'white', label: 'White', swatch: '#ffffff' },
            { key: 'blue', label: 'Blue', swatch: 'rgba(33,172,232,.35)' },
            { key: 'green', label: 'Green', swatch: 'rgba(52,183,67,.35)' },
            { key: 'yellow', label: 'Yellow', swatch: 'rgba(240,165,15,.4)' },
            { key: 'red', label: 'Red', swatch: 'rgba(209,46,46,.35)' },
            { key: 'pink', label: 'Pink', swatch: 'rgba(225,71,174,.35)' },
            { key: 'purple', label: 'Purple', swatch: 'rgba(135,85,236,.35)' },
            { key: 'accent', label: 'Accent', swatch: '#6366f1' },
        ];
    }

    static get DEFAULT_COLOR_SCHEME() {
        return 'blue';
    }

    static get sanitize() {
        return Object.assign({}, super.sanitize, { colorScheme: false });
    }

    constructor(params) {
        super(params);

        const requested = params.data && params.data.colorScheme;
        const isValid = QuoteColorScheme.COLOR_SCHEME_PRESETS.some((preset) => preset.key === requested);
        this.data.colorScheme = isValid ? requested : QuoteColorScheme.DEFAULT_COLOR_SCHEME;

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
        const colorItems = QuoteColorScheme.COLOR_SCHEME_PRESETS.map((preset) => ({
            icon: this._swatchIcon(preset.swatch),
            label: this.api.i18n.t(`${preset.label} color`),
            onActivate: () => this._setColorScheme(preset.key),
            isActive: this.data.colorScheme === preset.key,
            closeOnActivate: false,
        }));

        return [...alignmentItems, { type: 'separator' }, ...colorItems];
    }

    _setColorScheme(key) {
        this.data.colorScheme = key;
        this._applyColorPreview();
        this.block.dispatchChange();
    }

    _applyColorPreview() {
        if (!this._wrapperEl) {
            return;
        }

        const preset = QuoteColorScheme.COLOR_SCHEME_PRESETS.find((p) => p.key === this.data.colorScheme)
            || QuoteColorScheme.COLOR_SCHEME_PRESETS[0];

        this._wrapperEl.style.background = preset.swatch;
        this._wrapperEl.style.boxShadow = preset.key === 'white' ? 'inset 0 0 0 1px rgba(124,139,154,.35)' : 'none';
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
