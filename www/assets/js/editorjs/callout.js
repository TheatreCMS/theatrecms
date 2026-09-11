/**
 * Callout Plugin for Editor.js
 *
 * A block tool that renders a styled callout card with editable text, an
 * optional icon/label header, and a background color.
 *
 * The available colors are NOT hardcoded here: they come from the active
 * theme's `theme.json` (`settings.color.palette`, mirroring WordPress's
 * theme.json convention), injected server-side as `window.THEATRECMS_COLOR_PALETTE`
 * and passed into this tool's `config.colorPalette` (see editorjs-config.js) —
 * the same palette the quote block's color picker draws from (see
 * quote-color-scheme.js). This keeps the picker's options a property of the
 * active theme rather than of core code, and matches what
 * EditorJsHtmlConverter::renderCallout() resolves server-side when rendering
 * the saved `colorScheme` name.
 *
 * Usage:
 *   import Callout from './callout.js';
 *
 *   const editor = new EditorJS({
 *     tools: {
 *       callout: {
 *         class: Callout,
 *         inlineToolbar: true,
 *         config: { colorPalette: window.THEATRECMS_COLOR_PALETTE || [] },
 *       }
 *     }
 *   });
 */

class Callout {
    /**
     * Defines the toolbox entry shown in the Editor.js block picker.
     */
    static get toolbox() {
        return {
            title: 'Callout Card',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="3" ry="3"/>
        <line x1="2" y1="9" x2="22" y2="9"/>
        <circle cx="6.5" cy="6.5" r="0.5" fill="currentColor"/>
        <circle cx="9.5" cy="6.5" r="0.5" fill="currentColor"/>
        <circle cx="12.5" cy="6.5" r="0.5" fill="currentColor"/>
      </svg>`,
        };
    }

    /**
     * Tells Editor.js this tool supports read-only mode.
     */
    static get isReadOnlySupported() {
        return true;
    }

    /**
     * Tells Editor.js the data shape this block saves / restores.
     */
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
            colorScheme: false,
            icon: false,
            label: false,
        };
    }

    // Used only when a theme hasn't declared a settings.color.palette in its
    // theme.json, so the picker never has zero options.
    static get FALLBACK_COLOR_PALETTE() {
        return [{ name: 'grey', label: 'Grey', color: '#94a3b8' }];
    }

    // ─── Constructor ─────────────────────────────────────────────────────────────

    /**
     * @param {object} params
     * @param {object} params.data   – Previously saved block data (may be empty on first render).
     * @param {object} params.config – Tool configuration passed via EditorJS tools config.
     * @param {object} params.api   – Editor.js API object.
     * @param {boolean} params.readOnly – Whether the editor is in read-only mode.
     */
    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.config = config || {};

        const configuredPalette = this.config.colorPalette || [];
        this.colorPalette = configuredPalette.length > 0
            ? configuredPalette
            : Callout.FALLBACK_COLOR_PALETTE;

        const requested = data.colorScheme;
        const isValid = this.colorPalette.some((preset) => preset.name === requested);

        this.data = {
            text: data.text ?? '',
            colorScheme: isValid ? requested : this.colorPalette[0].name,
            label: data.label ?? '',
            icon: data.icon ?? '💡',
        };

        // DOM references populated in render()
        this._wrapper = null;
        this._textArea = null;
        this._labelInput = null;
        this._iconInput = null;
        this._settingsEl = null;
    }

    // ─── render() ────────────────────────────────────────────────────────────────

    /**
     * Called by Editor.js to get the DOM node for this block.
     * @returns {HTMLElement}
     */
    render() {
        this._wrapper = this._buildCard();
        return this._wrapper;
    }

    // ─── save() ──────────────────────────────────────────────────────────────────

    /**
     * Called by Editor.js when saving the document.
     * @returns {object} – Data object that will be stored in the output JSON.
     */
    save() {
        return {
            text: this._textArea ? this._textArea.innerHTML : this.data.text,
            label: this._labelInput ? this._labelInput.value : this.data.label,
            icon: this._iconInput ? this._iconInput.value : this.data.icon,
            colorScheme: this.data.colorScheme,
        };
    }

    // ─── validate() ──────────────────────────────────────────────────────────────

    /**
     * Return false to discard empty callout blocks on save.
     */
    validate(savedData) {
        return savedData.text.trim() !== '';
    }

    // ─── renderSettings() ────────────────────────────────────────────────────────

    /**
     * Renders the block-settings tray shown when the user clicks the ⋮ menu.
     * @returns {HTMLElement}
     */
    renderSettings() {
        const tray = document.createElement('div');
        tray.classList.add('ce-callout-settings');
        tray.style.cssText = `
      padding: 8px;
      min-width: 220px;
    `;

        // ── Section: Label ──────────────────────────────────────────────────────
        tray.appendChild(this._settingsSection('Label text', () => {
            const input = document.createElement('input');
            input.type = 'text';
            input.value = this.data.label;
            input.placeholder = 'e.g. Note, Warning, Tip…';
            input.style.cssText = this._inputStyle();
            input.addEventListener('input', () => {
                this.data.label = input.value;
                this._syncLabel();
            });
            this._labelInput = input;
            return input;
        }));

        // ── Section: Icon ───────────────────────────────────────────────────────
        tray.appendChild(this._settingsSection('Icon (emoji)', () => {
            const input = document.createElement('input');
            input.type = 'text';
            input.value = this.data.icon;
            input.placeholder = '💡';
            input.maxLength = 4;
            input.style.cssText = this._inputStyle() + 'width:60px;text-align:center;font-size:18px;';
            input.addEventListener('input', () => {
                this.data.icon = input.value;
                this._syncIcon();
            });
            this._iconInput = input;
            return input;
        }));

        // ── Section: Color ──────────────────────────────────────────────────────
        tray.appendChild(this._settingsSection('Color', () => {
            const grid = document.createElement('div');
            grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;';

            this.colorPalette.forEach((preset) => {
                const swatch = document.createElement('button');
                swatch.type = 'button';
                swatch.title = preset.label;
                const isActive = this.data.colorScheme === preset.name;
                swatch.style.cssText = `
          width:28px;height:28px;border-radius:6px;
          background:${preset.color};
          border:2.5px solid ${isActive ? '#6366f1' : 'rgba(124,139,154,.35)'};
          cursor:pointer;transition:transform .15s;
          outline:none;
        `;
                swatch.addEventListener('mouseenter', () => swatch.style.transform = 'scale(1.18)');
                swatch.addEventListener('mouseleave', () => swatch.style.transform = 'scale(1)');
                swatch.addEventListener('click', () => {
                    this.data.colorScheme = preset.name;
                    this._applyBackground();
                    Array.from(grid.children).forEach((child) => {
                        child.style.borderColor = 'rgba(124,139,154,.35)';
                    });
                    swatch.style.borderColor = '#6366f1';
                });
                grid.appendChild(swatch);
            });

            return grid;
        }));

        return tray;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────────

    /** Resolves the currently selected palette entry (falling back to the first). */
    _currentPreset() {
        return this.colorPalette.find((p) => p.name === this.data.colorScheme) || this.colorPalette[0];
    }

    /** Builds the full card DOM. */
    _buildCard() {
        const card = document.createElement('div');
        card.classList.add('ce-callout-card');
        card.style.cssText = `
      border-radius: 10px;
      background: ${this._currentPreset().color};
      padding: 14px 18px;
      margin: 4px 0;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
      transition: background .2s;
      font-family: inherit;
    `;

        // Header row (icon + label)
        const header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;';

        const iconSpan = document.createElement('span');
        iconSpan.classList.add('ce-callout-icon');
        iconSpan.textContent = this.data.icon || '💡';
        iconSpan.style.cssText = 'font-size:18px;line-height:1;flex-shrink:0;';

        const labelSpan = document.createElement('span');
        labelSpan.classList.add('ce-callout-label');
        labelSpan.textContent = this.data.label;
        labelSpan.style.cssText = `
      font-weight: 700;
      font-size: 0.78em;
      letter-spacing: .06em;
      text-transform: uppercase;
      opacity: .75;
    `;

        header.appendChild(iconSpan);
        header.appendChild(labelSpan);

        // Body text (contenteditable)
        const body = document.createElement('div');
        body.classList.add('ce-callout-text');
        body.contentEditable = this.readOnly ? 'false' : 'true';
        body.innerHTML = this.data.text;
        body.dataset.placeholder = 'Write your callout text here…';
        body.style.cssText = `
      outline: none;
      font-size: 0.95em;
      line-height: 1.6;
      min-height: 24px;
    `;

        // Placeholder via CSS emulation
        body.addEventListener('focus', () => body.classList.add('ce-callout-focused'));
        body.addEventListener('blur', () => body.classList.remove('ce-callout-focused'));

        // Inject shared stylesheet once
        Callout._injectStyles();

        card.appendChild(header);
        card.appendChild(body);

        this._textArea = body;
        this._iconEl = iconSpan;
        this._labelEl = labelSpan;
        this._card = card;

        return card;
    }

    /** Re-applies the background color to the live card. */
    _applyBackground() {
        if (!this._card) return;
        this._card.style.background = this._currentPreset().color;
    }

    /** Syncs the live label element with current data. */
    _syncLabel() {
        if (this._labelEl) this._labelEl.textContent = this.data.label;
    }

    /** Syncs the live icon element with current data. */
    _syncIcon() {
        if (this._iconEl) this._iconEl.textContent = this.data.icon;
    }

    /** Creates a labelled section wrapper for the settings tray. */
    _settingsSection(title, contentFn) {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'margin-bottom:12px;';

        const label = document.createElement('p');
        label.textContent = title;
        label.style.cssText = 'font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px;';

        wrap.appendChild(label);
        wrap.appendChild(contentFn());
        return wrap;
    }

    /** Shared input CSS string. */
    _inputStyle() {
        return `
      width:100%;box-sizing:border-box;
      border:1px solid #e5e7eb;border-radius:6px;
      padding:6px 8px;font-size:13px;
      outline:none;font-family:inherit;
      transition:border-color .15s;
    `;
    }

    /** Injects the plugin's shared stylesheet into <head> once. */
    static _injectStyles() {
        if (document.getElementById('ce-callout-styles')) return;
        const style = document.createElement('style');
        style.id = 'ce-callout-styles';
        style.textContent = `
      .ce-callout-text:empty:not(.ce-callout-focused)::before {
        content: attr(data-placeholder);
        color: #9ca3af;
        pointer-events: none;
      }
      .ce-callout-text br { display: block; }
      .ce-callout-settings input[type="text"]:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 2px rgba(99,102,241,.15);
      }
    `;
        document.head.appendChild(style);
    }
}

// Export for both ES modules and CommonJS environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Callout;
} else if (typeof define === 'function' && define.amd) {
    define([], () => Callout);
} else {
    window.Callout = Callout;
}
