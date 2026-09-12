/**
 * Carousel Plugin for Editor.js
 *
 * A block tool that holds an ordered collection of images with optional
 * captions, rendered on the frontend as a sliding carousel (arrows, dots,
 * optional autoplay) instead of a static grid/list. Images are added via
 * file picker and uploaded to the server immediately on selection — the
 * slide data shape is identical to the imageGallery block.
 *
 * Block data shape:
 * {
 *   "items":         [{ "url": "/uploads/…", "caption": "Alt text" }, …],
 *   "autoplay":      false,
 *   "autoplaySpeed": 3000,
 *   "showArrows":    true,
 *   "showDots":      true
 * }
 *
 * Tool configuration (via buildEditorJsConfig):
 * {
 *   uploadEndpoint: '/admin/images/upload'   // POST multipart, field: image
 * }
 *
 * The server-side EditorJsHtmlConverter renders the block as:
 * <div class="editorjs-carousel" data-autoplay="true" data-autoplay-speed="3000" data-arrows="true" data-dots="true">
 *   <figure class="editorjs-carousel__slide">…</figure>
 *   …
 * </div>
 */

class Carousel {
    static get toolbox() {
        return {
            title: 'Carousel',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="6" y="4" width="12" height="16" rx="2" ry="2"/>
        <path d="M2 9v6"/>
        <path d="M22 9v6"/>
      </svg>`,
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    static get sanitize() {
        return {
            items:          false,
            autoplay:       false,
            autoplaySpeed:  false,
            showArrows:     false,
            showDots:       false,
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api      = api;
        this.readOnly = readOnly;
        this.config   = config || {};

        this._uploadEndpoint = this.config.uploadEndpoint || '/admin/images/upload';

        this.data = {
            items:         Array.isArray(data.items) ? data.items : [],
            autoplay:      !!data.autoplay,
            autoplaySpeed: Number.isFinite(data.autoplaySpeed) ? data.autoplaySpeed : 3000,
            showArrows:    data.showArrows !== undefined ? !!data.showArrows : true,
            showDots:      data.showDots !== undefined ? !!data.showDots : true,
        };

        this._wrapper = null;
        this._list    = null;
    }

    // ─── render ──────────────────────────────────────────────────────────────

    render() {
        Carousel._injectStyles();

        this._wrapper = document.createElement('div');
        this._wrapper.classList.add('ce-carousel');

        this._list = document.createElement('div');
        this._list.classList.add('ce-carousel__list');

        this.data.items.forEach(item => this._appendUploadedRow(item.url, item.caption || ''));

        this._wrapper.appendChild(this._list);

        if (!this.readOnly) {
            this._wrapper.appendChild(this._buildAddButton());
        }

        return this._wrapper;
    }

    // ─── save ────────────────────────────────────────────────────────────────

    save() {
        const rows = this._list ? this._list.querySelectorAll('.ce-carousel__row') : [];
        const items = [];

        rows.forEach(row => {
            const url     = row.dataset.url     || '';
            const caption = (row.querySelector('.ce-carousel__caption-input')?.value || '').trim();
            if (url) {
                items.push({ url, caption });
            }
        });

        return {
            items,
            autoplay:      this.data.autoplay,
            autoplaySpeed: this.data.autoplaySpeed,
            showArrows:    this.data.showArrows,
            showDots:      this.data.showDots,
        };
    }

    // ─── validate ────────────────────────────────────────────────────────────

    validate(savedData) {
        return Array.isArray(savedData.items) && savedData.items.length > 0;
    }

    // ─── renderSettings ──────────────────────────────────────────────────────

    renderSettings() {
        const tray = document.createElement('div');
        tray.style.cssText = 'padding:8px;width:180px;box-sizing:border-box;';

        const label = document.createElement('p');
        label.textContent = 'Carousel settings';
        label.style.cssText = 'font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px;';
        tray.appendChild(label);

        tray.appendChild(this._buildToggleRow('Autoplay', 'autoplay'));
        tray.appendChild(this._buildToggleRow('Arrows', 'showArrows'));
        tray.appendChild(this._buildToggleRow('Dots', 'showDots'));
        tray.appendChild(this._buildSpeedInput());

        return tray;
    }

    // ─── private: build a single boolean toggle button row ────────────────────

    _buildToggleRow(labelText, key) {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:8px;';

        const label = document.createElement('span');
        label.textContent = labelText;
        label.style.cssText = 'font-size:12px;color:#374151;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';

        const btn = document.createElement('button');
        btn.type = 'button';
        const paint = (active) => {
            btn.style.cssText = `
                flex-shrink:0;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:500;cursor:pointer;
                border:1px solid ${active ? '#6366f1' : '#e5e7eb'};
                background:${active ? '#eef2ff' : '#fff'};
                color:${active ? '#4f46e5' : '#374151'};
            `;
            btn.textContent = active ? 'On' : 'Off';
        };
        paint(this.data[key]);

        btn.addEventListener('click', () => {
            this.data[key] = !this.data[key];
            paint(this.data[key]);
        });

        row.appendChild(label);
        row.appendChild(btn);
        return row;
    }

    // ─── private: build the autoplay-speed number input ───────────────────────

    _buildSpeedInput() {
        const row = document.createElement('div');

        const label = document.createElement('span');
        label.textContent = 'Autoplay speed (ms)';
        label.style.cssText = 'display:block;font-size:12px;color:#374151;margin-bottom:4px;';

        const input = document.createElement('input');
        input.type = 'number';
        input.min = '1000';
        input.step = '500';
        input.value = this.data.autoplaySpeed;
        input.style.cssText = 'width:100%;box-sizing:border-box;border:1px solid #e5e7eb;border-radius:5px;padding:4px 6px;font-size:12px;font-family:inherit;';
        input.addEventListener('change', () => {
            const value = parseInt(input.value, 10);
            this.data.autoplaySpeed = Number.isFinite(value) && value >= 1000 ? value : 3000;
            input.value = this.data.autoplaySpeed;
        });

        row.appendChild(label);
        row.appendChild(input);
        return row;
    }

    // ─── private: build the "Add images" button ───────────────────────────────

    _buildAddButton() {
        const wrapper = document.createElement('div');
        wrapper.classList.add('ce-carousel__add-zone');

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.multiple = true;
        fileInput.style.display = 'none';
        fileInput.addEventListener('change', () => {
            Array.from(fileInput.files || []).forEach(file => this._handleFile(file));
            fileInput.value = '';
        });

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.classList.add('ce-carousel__add-btn');
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add slides`;
        btn.addEventListener('click', () => fileInput.click());

        wrapper.appendChild(fileInput);
        wrapper.appendChild(btn);
        return wrapper;
    }

    // ─── private: handle a picked File object ─────────────────────────────────

    async _handleFile(file) {
        const row = this._appendLoadingRow(file.name);

        try {
            const url = await this._uploadFile(file);
            this._resolveRow(row, url);
        } catch (err) {
            this._rejectRow(row, err.message || 'Upload failed');
        }
    }

    // ─── private: upload a File to the server ─────────────────────────────────

    async _uploadFile(file) {
        const formData = new FormData();
        formData.append('image', file);

        const resp = await fetch(this._uploadEndpoint, { method: 'POST', body: formData });

        if (!resp.ok) {
            throw new Error(`Server error: ${resp.status}`);
        }

        const data = await resp.json();

        if (data.success !== 1 || !data.file?.url) {
            throw new Error(data.error?.message || 'Upload failed');
        }

        return data.file.url;
    }

    // ─── private: append a loading placeholder row ────────────────────────────

    _appendLoadingRow(filename) {
        const row = document.createElement('div');
        row.classList.add('ce-carousel__row', 'ce-carousel__row--loading');

        const thumb = document.createElement('div');
        thumb.classList.add('ce-carousel__thumb');
        thumb.innerHTML = `<span class="ce-carousel__spinner"></span>`;

        const name = document.createElement('span');
        name.classList.add('ce-carousel__filename');
        name.textContent = filename;

        row.appendChild(thumb);
        row.appendChild(name);
        this._list.appendChild(row);
        return row;
    }

    // ─── private: replace loading row with uploaded image ─────────────────────

    _resolveRow(row, url) {
        row.classList.remove('ce-carousel__row--loading');
        row.classList.add('ce-carousel__row--done');
        row.dataset.url = url;
        row.innerHTML = '';

        const thumb = document.createElement('div');
        thumb.classList.add('ce-carousel__thumb');

        const img = document.createElement('img');
        img.classList.add('ce-carousel__thumb-img');
        img.src = url;
        img.alt = '';
        thumb.appendChild(img);

        const captionInput = document.createElement('input');
        captionInput.type = 'text';
        captionInput.classList.add('ce-carousel__caption-input');
        captionInput.placeholder = 'Caption (optional)…';

        const removeBtn = this._buildRemoveBtn(row);

        row.appendChild(thumb);
        row.appendChild(captionInput);
        row.appendChild(removeBtn);
    }

    // ─── private: mark row as failed ─────────────────────────────────────────

    _rejectRow(row, message) {
        row.classList.remove('ce-carousel__row--loading');
        row.classList.add('ce-carousel__row--error');
        row.innerHTML = '';

        const icon = document.createElement('span');
        icon.classList.add('ce-carousel__error-icon');
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;

        const msg = document.createElement('span');
        msg.classList.add('ce-carousel__error-msg');
        msg.textContent = message;

        const removeBtn = this._buildRemoveBtn(row);

        row.appendChild(icon);
        row.appendChild(msg);
        row.appendChild(removeBtn);
    }

    // ─── private: append a row for already-uploaded data (edit mode) ──────────

    _appendUploadedRow(url, caption) {
        const row = document.createElement('div');
        row.classList.add('ce-carousel__row', 'ce-carousel__row--done');
        row.dataset.url = url;

        const thumb = document.createElement('div');
        thumb.classList.add('ce-carousel__thumb');

        const img = document.createElement('img');
        img.classList.add('ce-carousel__thumb-img');
        img.src = url;
        img.alt = '';
        thumb.appendChild(img);

        const captionInput = document.createElement('input');
        captionInput.type = 'text';
        captionInput.classList.add('ce-carousel__caption-input');
        captionInput.placeholder = 'Caption (optional)…';
        captionInput.value = caption;

        row.appendChild(thumb);
        row.appendChild(captionInput);

        if (!this.readOnly) {
            row.appendChild(this._buildRemoveBtn(row));
        }

        this._list.appendChild(row);
    }

    // ─── private: remove button ───────────────────────────────────────────────

    _buildRemoveBtn(row) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.classList.add('ce-carousel__remove-btn');
        btn.title = 'Remove slide';
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
        btn.addEventListener('click', () => row.remove());
        return btn;
    }

    // ─── styles ───────────────────────────────────────────────────────────────

    static _injectStyles() {
        if (document.getElementById('ce-carousel-styles')) return;
        const style = document.createElement('style');
        style.id = 'ce-carousel-styles';
        style.textContent = `
            .ce-carousel {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 12px;
                background: #fafafa;
            }
            .ce-carousel__list {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 8px;
            }
            .ce-carousel__row {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 8px 10px;
            }
            .ce-carousel__row--loading {
                opacity: 0.7;
            }
            .ce-carousel__row--error {
                border-color: #fca5a5;
                background: #fef2f2;
            }
            .ce-carousel__thumb {
                width: 56px;
                height: 42px;
                flex-shrink: 0;
                border-radius: 5px;
                overflow: hidden;
                border: 1px solid #e5e7eb;
                background: #f3f4f6;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ce-carousel__thumb-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .ce-carousel__filename {
                flex: 1;
                font-size: 12px;
                color: #6b7280;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .ce-carousel__error-icon {
                flex-shrink: 0;
                color: #ef4444;
                display: flex;
            }
            .ce-carousel__error-msg {
                flex: 1;
                font-size: 12px;
                color: #ef4444;
            }
            .ce-carousel__caption-input {
                flex: 1;
                box-sizing: border-box;
                border: 1px solid #e5e7eb;
                border-radius: 5px;
                padding: 5px 8px;
                font-size: 12px;
                color: #6b7280;
                font-family: inherit;
                outline: none;
                transition: border-color .15s;
                background: #fff;
            }
            .ce-carousel__caption-input:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 2px rgba(99,102,241,.1);
            }
            .ce-carousel__remove-btn {
                flex-shrink: 0;
                width: 28px;
                height: 28px;
                border: none;
                background: transparent;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #9ca3af;
                transition: color .15s, background .15s;
            }
            .ce-carousel__remove-btn:hover {
                color: #ef4444;
                background: #fef2f2;
            }
            .ce-carousel__add-zone {
                width: 100%;
            }
            .ce-carousel__add-btn {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 7px 12px;
                border: 1.5px dashed #d1d5db;
                border-radius: 7px;
                background: transparent;
                color: #6b7280;
                font-size: 13px;
                font-family: inherit;
                cursor: pointer;
                width: 100%;
                justify-content: center;
                transition: border-color .15s, color .15s;
            }
            .ce-carousel__add-btn:hover {
                border-color: #6366f1;
                color: #4f46e5;
            }
            @keyframes ce-carousel-spin {
                to { transform: rotate(360deg); }
            }
            .ce-carousel__spinner {
                display: inline-block;
                width: 18px;
                height: 18px;
                border: 2px solid #e5e7eb;
                border-top-color: #6366f1;
                border-radius: 50%;
                animation: ce-carousel-spin .7s linear infinite;
            }
        `;
        document.head.appendChild(style);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = Carousel;
} else if (typeof define === 'function' && define.amd) {
    define([], () => Carousel);
} else {
    window.Carousel = Carousel;
}
