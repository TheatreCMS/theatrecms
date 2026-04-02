/**
 * Callout Plugin for Editor.js
 *
 * A block tool that renders a styled callout card with
 * editable text and a customizable background color.
 *
 * Usage:
 *   import Callout from './callout-card-plugin.js';
 *
 *   const editor = new EditorJS({
 *     tools: {
 *       callout: {
 *         class: Callout,
 *         inlineToolbar: true,
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
            backgroundColor: false,
            textColor: false,
            icon: false,
            label: false,
        };
    }

    // ─── Preset palette shown in the color picker ───────────────────────────────

    static get COLOR_PRESETS() {
        return [
            { label: 'Amber', bg: '#FFF8E7', border: '#F59E0B', text: '#92400E' },
            { label: 'Sky', bg: '#EFF6FF', border: '#3B82F6', text: '#1E40AF' },
            { label: 'Emerald', bg: '#ECFDF5', border: '#10B981', text: '#065F46' },
            { label: 'Rose', bg: '#FFF1F2', border: '#F43F5E', text: '#9F1239' },
            { label: 'Violet', bg: '#F5F3FF', border: '#8B5CF6', text: '#4C1D95' },
            { label: 'Slate', bg: '#F8FAFC', border: '#64748B', text: '#1E293B' },
            { label: 'Coral', bg: '#FFF4F0', border: '#F97316', text: '#7C2D12' },
            { label: 'Teal', bg: '#F0FDFA', border: '#14B8A6', text: '#134E4A' },
        ];
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

        // Merge saved data with sensible defaults
        const defaultPreset = Callout.COLOR_PRESETS[0];
        this.data = {
            text: data.text ?? '',
            backgroundColor: data.backgroundColor ?? defaultPreset.bg,
            borderColor: data.borderColor ?? defaultPreset.border,
            textColor: data.textColor ?? defaultPreset.text,
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
            backgroundColor: this.data.backgroundColor,
            borderColor: this.data.borderColor,
            textColor: this.data.textColor,
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
            return input;
        }));

        // ── Section: Color presets ──────────────────────────────────────────────
        tray.appendChild(this._settingsSection('Color preset', () => {
            const grid = document.createElement('div');
            grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;';

            Callout.COLOR_PRESETS.forEach(preset => {
                const swatch = document.createElement('button');
                swatch.title = preset.label;
                swatch.style.cssText = `
          width:28px;height:28px;border-radius:6px;
          background:${preset.bg};
          border:2.5px solid ${preset.border};
          cursor:pointer;transition:transform .15s;
          outline:none;
        `;
                swatch.addEventListener('mouseenter', () => swatch.style.transform = 'scale(1.18)');
                swatch.addEventListener('mouseleave', () => swatch.style.transform = 'scale(1)');
                swatch.addEventListener('click', () => {
                    this.data.backgroundColor = preset.bg;
                    this.data.borderColor = preset.border;
                    this.data.textColor = preset.text;
                    this._applyColors();
                });
                grid.appendChild(swatch);
            });

            return grid;
        }));

        // ── Section: Custom colors ──────────────────────────────────────────────
        tray.appendChild(this._settingsSection('Custom colors', () => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;gap:10px;align-items:center;flex-wrap:wrap;';

            const addColorPicker = (label, key) => {
                const wrap = document.createElement('label');
                wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:2px;font-size:10px;color:#6b7280;';
                wrap.textContent = label;

                const picker = document.createElement('input');
                picker.type = 'color';
                picker.value = this.data[key];
                picker.style.cssText = 'width:32px;height:32px;border:none;padding:0;cursor:pointer;border-radius:6px;';
                picker.addEventListener('input', () => {
                    this.data[key] = picker.value;
                    this._applyColors();
                });

                wrap.appendChild(picker);
                return wrap;
            };

            row.appendChild(addColorPicker('BG', 'backgroundColor'));
            row.appendChild(addColorPicker('Border', 'borderColor'));
            row.appendChild(addColorPicker('Text', 'textColor'));
            return row;
        }));

        return tray;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────────

    /** Builds the full card DOM. */
    _buildCard() {
        const card = document.createElement('div');
        card.classList.add('ce-callout-card');
        card.style.cssText = `
      border-radius: 10px;
      border-left: 4px solid ${this.data.borderColor};
      background: ${this.data.backgroundColor};
      padding: 14px 18px 14px 16px;
      margin: 4px 0;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
      transition: border-color .2s, background .2s;
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
      color: ${this.data.textColor};
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
      color: ${this.data.textColor};
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

    /** Re-applies all color CSS properties to the live card. */
    _applyColors() {
        if (!this._card) return;
        this._card.style.borderLeftColor = this.data.borderColor;
        this._card.style.background = this.data.backgroundColor;
        if (this._textArea) this._textArea.style.color = this.data.textColor;
        if (this._labelEl) this._labelEl.style.color = this.data.textColor;
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
      .ce-callout-settings input[type="text"]:focus,
      .ce-callout-settings input[type="color"]:focus {
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