/**
 * CtaCard Plugin for Editor.js
 *
 * A block tool that renders a call-to-action card: a short message plus a
 * button, on a colored background. Mirrors Ghost's kg-cta-card (immersive,
 * centered variant) so the frontend renderer (EditorJsHtmlConverter) can
 * emit markup that reuses the theme's existing cards.min.css.
 *
 * Usage:
 *   import CtaCard from './cta-card.js';
 *
 *   const editor = new EditorJS({
 *     tools: {
 *       ctaCard: {
 *         class: CtaCard,
 *         inlineToolbar: true,
 *       }
 *     }
 *   });
 */

class CtaCard {
    static get toolbox() {
        return {
            title: 'CTA Card',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="3"/>
        <path d="M7 12h6"/>
        <path d="M7 8h10"/>
        <rect x="7" y="15" width="7" height="3" rx="1"/>
      </svg>`,
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    static get sanitize() {
        return {
            text: {
                br: true,
                b: true,
                i: true,
                a: { href: true },
                mark: true,
                code: true,
            },
            buttonText: false,
            buttonUrl: false,
            backgroundColor: false,
        };
    }

    // Mirrors Ghost's kg-cta-bg-* classes so the frontend renders identically.
    static get BACKGROUND_PRESETS() {
        return [
            { key: 'purple', label: 'Purple', swatch: 'rgba(135,85,236,.25)' },
            { key: 'blue', label: 'Blue', swatch: 'rgba(33,172,232,.25)' },
            { key: 'green', label: 'Green', swatch: 'rgba(52,183,67,.25)' },
            { key: 'yellow', label: 'Yellow', swatch: 'rgba(240,165,15,.25)' },
            { key: 'red', label: 'Red', swatch: 'rgba(209,46,46,.25)' },
            { key: 'pink', label: 'Pink', swatch: 'rgba(225,71,174,.25)' },
            { key: 'grey', label: 'Grey', swatch: 'rgba(151,163,175,.25)' },
            { key: 'white', label: 'White', swatch: '#ffffff' },
        ];
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};

        this.data = {
            text: data.text ?? '',
            buttonText: data.buttonText ?? '',
            buttonUrl: data.buttonUrl ?? '',
            backgroundColor: data.backgroundColor ?? CtaCard.BACKGROUND_PRESETS[0].key,
        };

        this._wrapper = null;
        this._textArea = null;
        this._buttonTextInput = null;
        this._buttonUrlInput = null;
    }

    render() {
        this._wrapper = this._buildCard();
        return this._wrapper;
    }

    save() {
        return {
            text: this._textArea ? this._textArea.innerHTML : this.data.text,
            buttonText: this._buttonTextInput ? this._buttonTextInput.value : this.data.buttonText,
            buttonUrl: this._buttonUrlInput ? this._buttonUrlInput.value : this.data.buttonUrl,
            backgroundColor: this.data.backgroundColor,
        };
    }

    validate(savedData) {
        return savedData.text.trim() !== '' && savedData.buttonUrl.trim() !== '';
    }

    renderSettings() {
        const tray = document.createElement('div');
        tray.classList.add('ce-cta-settings');
        tray.style.cssText = 'padding: 8px; min-width: 220px;';

        const label = document.createElement('p');
        label.textContent = 'Background color';
        label.style.cssText = 'font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px;';
        tray.appendChild(label);

        const grid = document.createElement('div');
        grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;';

        CtaCard.BACKGROUND_PRESETS.forEach((preset) => {
            const swatch = document.createElement('button');
            swatch.type = 'button';
            swatch.title = preset.label;
            const isActive = this.data.backgroundColor === preset.key;
            swatch.style.cssText = `
        width:28px;height:28px;border-radius:6px;
        background:${preset.swatch};
        border:2.5px solid ${isActive ? '#6366f1' : 'rgba(124,139,154,.35)'};
        cursor:pointer;outline:none;
      `;
            swatch.addEventListener('click', () => {
                this.data.backgroundColor = preset.key;
                this._applyBackground();
                Array.from(grid.children).forEach((child) => {
                    child.style.borderColor = 'rgba(124,139,154,.35)';
                });
                swatch.style.borderColor = '#6366f1';
            });
            grid.appendChild(swatch);
        });

        tray.appendChild(grid);
        return tray;
    }

    _buildCard() {
        const preset = CtaCard.BACKGROUND_PRESETS.find((p) => p.key === this.data.backgroundColor) || CtaCard.BACKGROUND_PRESETS[0];

        const card = document.createElement('div');
        card.classList.add('ce-cta-card');
        card.style.cssText = `
      border-radius: 8px;
      padding: 1.5em;
      text-align: center;
      font-family: inherit;
    `;
        card.style.background = preset.swatch;
        this._card = card;

        const text = document.createElement('div');
        text.classList.add('ce-cta-text');
        text.contentEditable = this.readOnly ? 'false' : 'true';
        text.innerHTML = this.data.text;
        text.dataset.placeholder = 'Reserve your seats now through the CAPA ticketing portal.';
        text.style.cssText = 'outline:none;font-weight:700;font-size:1.05em;line-height:1.5;min-height:24px;margin-bottom:1em;';

        const buttonRow = document.createElement('div');
        buttonRow.style.cssText = 'display:flex;gap:10px;justify-content:center;flex-wrap:wrap;';

        const buttonTextInput = document.createElement('input');
        buttonTextInput.type = 'text';
        buttonTextInput.value = this.data.buttonText;
        buttonTextInput.placeholder = 'Button text (e.g. Go to CAPA Ticketing →)';
        buttonTextInput.readOnly = this.readOnly;
        buttonTextInput.style.cssText = this._inputStyle() + 'flex:1;min-width:220px;';

        const buttonUrlInput = document.createElement('input');
        buttonUrlInput.type = 'url';
        buttonUrlInput.value = this.data.buttonUrl;
        buttonUrlInput.placeholder = 'https://…';
        buttonUrlInput.readOnly = this.readOnly;
        buttonUrlInput.style.cssText = this._inputStyle() + 'flex:1;min-width:220px;';

        buttonRow.appendChild(buttonTextInput);
        buttonRow.appendChild(buttonUrlInput);

        CtaCard._injectStyles();

        card.appendChild(text);
        card.appendChild(buttonRow);

        this._textArea = text;
        this._buttonTextInput = buttonTextInput;
        this._buttonUrlInput = buttonUrlInput;

        return card;
    }

    _applyBackground() {
        if (!this._card) return;
        const preset = CtaCard.BACKGROUND_PRESETS.find((p) => p.key === this.data.backgroundColor) || CtaCard.BACKGROUND_PRESETS[0];
        this._card.style.background = preset.swatch;
    }

    _inputStyle() {
        return `
      box-sizing:border-box;
      border:1px solid #e5e7eb;border-radius:6px;
      padding:8px 10px;font-size:13px;
      outline:none;font-family:inherit;
      transition:border-color .15s;
    `;
    }

    static _injectStyles() {
        if (document.getElementById('ce-cta-card-styles')) return;
        const style = document.createElement('style');
        style.id = 'ce-cta-card-styles';
        style.textContent = `
      .ce-cta-text:empty:before {
        content: attr(data-placeholder);
        color: #9ca3af;
        pointer-events: none;
      }
      .ce-cta-settings button:focus {
        border-color: #6366f1 !important;
      }
    `;
        document.head.appendChild(style);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = CtaCard;
} else if (typeof define === 'function' && define.amd) {
    define([], () => CtaCard);
} else {
    window.CtaCard = CtaCard;
}
