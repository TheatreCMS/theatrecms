/**
 * ImageGallery Plugin for Editor.js
 *
 * A block tool that holds an ordered collection of images with optional
 * captions. Images are added via file picker (or drag-and-drop onto the
 * add button) and uploaded to the server immediately on selection.
 *
 * Block data shape:
 * {
 *   "items":   [{ "url": "/uploads/…", "caption": "Alt text" }, …],
 *   "caption": "Optional gallery caption",
 *   "layout":  "grid" | "list"
 * }
 *
 * Tool configuration (via buildEditorJsConfig):
 * {
 *   uploadEndpoint: '/admin/images/upload'   // POST multipart, field: image
 * }
 *
 * The server-side EditorJsHtmlConverter renders the block as:
 * <figure class="editorjs-gallery-wrap">
 *   <div class="editorjs-gallery" data-layout="grid">
 *     <figure class="editorjs-gallery__item">…</figure>
 *     …
 *   </div>
 *   <figcaption>Optional gallery caption</figcaption>  <!-- omitted if empty -->
 * </figure>
 */

class ImageGallery {
    static get toolbox() {
        return {
            title: 'Image Gallery',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
        <circle cx="8.5" cy="8.5" r="1.5"/>
        <polyline points="21 15 16 10 5 21"/>
      </svg>`,
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    static get sanitize() {
        return {
            items:  false,
            layout: false,
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api      = api;
        this.readOnly = readOnly;
        this.config   = config || {};

        this._uploadEndpoint = this.config.uploadEndpoint || '/admin/images/upload';

        this.data = {
            items:   Array.isArray(data.items) ? data.items : [],
            caption: data.caption || '',
            layout:  data.layout || 'grid',
        };

        this._wrapper       = null;
        this._list          = null;
        this._captionInput  = null;
    }

    // ─── render ──────────────────────────────────────────────────────────────

    render() {
        ImageGallery._injectStyles();

        this._wrapper = document.createElement('div');
        this._wrapper.classList.add('ce-gallery');

        this._list = document.createElement('div');
        this._list.classList.add('ce-gallery__list');

        this.data.items.forEach(item => this._appendUploadedRow(item.url, item.caption || ''));

        this._wrapper.appendChild(this._list);

        if (!this.readOnly) {
            this._wrapper.appendChild(this._buildAddButton());
            this._wrapper.appendChild(this._buildGalleryCaption());
        }

        return this._wrapper;
    }

    // ─── save ────────────────────────────────────────────────────────────────

    save() {
        const rows = this._list ? this._list.querySelectorAll('.ce-gallery__row') : [];
        const items = [];

        rows.forEach(row => {
            const url     = row.dataset.url     || '';
            const caption = (row.querySelector('.ce-gallery__caption-input')?.value || '').trim();
            if (url) {
                items.push({ url, caption });
            }
        });

        return { items, layout: this.data.layout, caption: (this._captionInput?.value || '').trim() };
    }

    // ─── validate ────────────────────────────────────────────────────────────

    validate(savedData) {
        return Array.isArray(savedData.items) && savedData.items.length > 0;
    }

    // ─── renderSettings ──────────────────────────────────────────────────────

    renderSettings() {
        const tray = document.createElement('div');
        tray.style.cssText = 'padding:8px;min-width:180px;';

        const label = document.createElement('p');
        label.textContent = 'Layout';
        label.style.cssText = 'font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px;';
        tray.appendChild(label);

        const layouts = [
            {
                value: 'grid',
                label: 'Grid',
                icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>`,
            },
            {
                value: 'list',
                label: 'List',
                icon: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>`,
            },
        ];

        const btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:6px;';

        layouts.forEach(({ value, label: btnLabel, icon }) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const active = this.data.layout === value;
            btn.style.cssText = `
                display:flex;align-items:center;gap:6px;padding:6px 10px;
                border-radius:6px;border:1px solid ${active ? '#6366f1' : '#e5e7eb'};
                background:${active ? '#eef2ff' : '#fff'};
                color:${active ? '#4f46e5' : '#374151'};
                font-size:12px;font-weight:500;cursor:pointer;
            `;
            btn.innerHTML = `${icon} ${btnLabel}`;
            btn.addEventListener('click', () => {
                this.data.layout = value;
                tray.querySelectorAll('button').forEach(b => {
                    b.style.borderColor = '#e5e7eb';
                    b.style.background  = '#fff';
                    b.style.color       = '#374151';
                });
                btn.style.borderColor = '#6366f1';
                btn.style.background  = '#eef2ff';
                btn.style.color       = '#4f46e5';
            });
            btnRow.appendChild(btn);
        });

        tray.appendChild(btnRow);
        return tray;
    }

    // ─── private: build the "Add images" button ───────────────────────────────

    _buildAddButton() {
        const wrapper = document.createElement('div');
        wrapper.classList.add('ce-gallery__add-zone');

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
        btn.classList.add('ce-gallery__add-btn');
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add images`;
        btn.addEventListener('click', () => fileInput.click());

        wrapper.appendChild(fileInput);
        wrapper.appendChild(btn);
        return wrapper;
    }

    // ─── private: build the gallery-level caption input ──────────────────────

    _buildGalleryCaption() {
        const input = document.createElement('input');
        input.type = 'text';
        input.classList.add('ce-gallery__gallery-caption');
        input.placeholder = 'Gallery caption (optional)…';
        input.value = this.data.caption || '';
        this._captionInput = input;
        return input;
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
        row.classList.add('ce-gallery__row', 'ce-gallery__row--loading');

        const thumb = document.createElement('div');
        thumb.classList.add('ce-gallery__thumb');
        thumb.innerHTML = `<span class="ce-gallery__spinner"></span>`;

        const name = document.createElement('span');
        name.classList.add('ce-gallery__filename');
        name.textContent = filename;

        row.appendChild(thumb);
        row.appendChild(name);
        this._list.appendChild(row);
        return row;
    }

    // ─── private: replace loading row with uploaded image ─────────────────────

    _resolveRow(row, url) {
        row.classList.remove('ce-gallery__row--loading');
        row.classList.add('ce-gallery__row--done');
        row.dataset.url = url;
        row.innerHTML = '';

        const thumb = document.createElement('div');
        thumb.classList.add('ce-gallery__thumb');

        const img = document.createElement('img');
        img.classList.add('ce-gallery__thumb-img');
        img.src = url;
        img.alt = '';
        thumb.appendChild(img);

        const captionInput = document.createElement('input');
        captionInput.type = 'text';
        captionInput.classList.add('ce-gallery__caption-input');
        captionInput.placeholder = 'Caption (optional)…';

        const removeBtn = this._buildRemoveBtn(row);

        row.appendChild(thumb);
        row.appendChild(captionInput);
        row.appendChild(removeBtn);
    }

    // ─── private: mark row as failed ─────────────────────────────────────────

    _rejectRow(row, message) {
        row.classList.remove('ce-gallery__row--loading');
        row.classList.add('ce-gallery__row--error');
        row.innerHTML = '';

        const icon = document.createElement('span');
        icon.classList.add('ce-gallery__error-icon');
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;

        const msg = document.createElement('span');
        msg.classList.add('ce-gallery__error-msg');
        msg.textContent = message;

        const removeBtn = this._buildRemoveBtn(row);

        row.appendChild(icon);
        row.appendChild(msg);
        row.appendChild(removeBtn);
    }

    // ─── private: append a row for already-uploaded data (edit mode) ──────────

    _appendUploadedRow(url, caption) {
        const row = document.createElement('div');
        row.classList.add('ce-gallery__row', 'ce-gallery__row--done');
        row.dataset.url = url;

        const thumb = document.createElement('div');
        thumb.classList.add('ce-gallery__thumb');

        const img = document.createElement('img');
        img.classList.add('ce-gallery__thumb-img');
        img.src = url;
        img.alt = '';
        thumb.appendChild(img);

        const captionInput = document.createElement('input');
        captionInput.type = 'text';
        captionInput.classList.add('ce-gallery__caption-input');
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
        btn.classList.add('ce-gallery__remove-btn');
        btn.title = 'Remove image';
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
        btn.addEventListener('click', () => row.remove());
        return btn;
    }

    // ─── styles ───────────────────────────────────────────────────────────────

    static _injectStyles() {
        if (document.getElementById('ce-gallery-styles')) return;
        const style = document.createElement('style');
        style.id = 'ce-gallery-styles';
        style.textContent = `
            .ce-gallery {
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 12px;
                background: #fafafa;
            }
            .ce-gallery__list {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 8px;
            }
            .ce-gallery__row {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 8px 10px;
            }
            .ce-gallery__row--loading {
                opacity: 0.7;
            }
            .ce-gallery__row--error {
                border-color: #fca5a5;
                background: #fef2f2;
            }
            .ce-gallery__thumb {
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
            .ce-gallery__thumb-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .ce-gallery__filename {
                flex: 1;
                font-size: 12px;
                color: #6b7280;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .ce-gallery__error-icon {
                flex-shrink: 0;
                color: #ef4444;
                display: flex;
            }
            .ce-gallery__error-msg {
                flex: 1;
                font-size: 12px;
                color: #ef4444;
            }
            .ce-gallery__caption-input {
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
            .ce-gallery__caption-input:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 2px rgba(99,102,241,.1);
            }
            .ce-gallery__remove-btn {
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
            .ce-gallery__remove-btn:hover {
                color: #ef4444;
                background: #fef2f2;
            }
            .ce-gallery__add-zone {
                width: 100%;
            }
            .ce-gallery__add-btn {
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
            .ce-gallery__add-btn:hover {
                border-color: #6366f1;
                color: #4f46e5;
            }
            .ce-gallery__gallery-caption {
                display: block;
                width: 100%;
                box-sizing: border-box;
                margin-top: 8px;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 6px 10px;
                font-size: 13px;
                font-family: inherit;
                color: #374151;
                outline: none;
                transition: border-color .15s;
                background: #fff;
            }
            .ce-gallery__gallery-caption:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 2px rgba(99,102,241,.1);
            }
            @keyframes ce-gallery-spin {
                to { transform: rotate(360deg); }
            }
            .ce-gallery__spinner {
                display: inline-block;
                width: 18px;
                height: 18px;
                border: 2px solid #e5e7eb;
                border-top-color: #6366f1;
                border-radius: 50%;
                animation: ce-gallery-spin .7s linear infinite;
            }
        `;
        document.head.appendChild(style);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImageGallery;
} else if (typeof define === 'function' && define.amd) {
    define([], () => ImageGallery);
} else {
    window.ImageGallery = ImageGallery;
}
