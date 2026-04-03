/**
 * Sponsor Block Plugin for Editor.js
 *
 * Renders a styled sponsor card with an optional logo image,
 * an editable sponsor name, and an optional website URL.
 *
 * Saved data shape:
 *   { name: string, logoUrl: string, websiteUrl: string }
 */

(function (global, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        global.SponsorBlock = factory();
    }
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this, function () {

    const CSS = `
        .sponsor-block {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }
        .sponsor-block__logo-wrap {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            overflow: hidden;
            cursor: pointer;
        }
        .sponsor-block__logo-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .sponsor-block__logo-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: #9ca3af;
            font-size: 10px;
            text-align: center;
            line-height: 1.2;
        }
        .sponsor-block__logo-placeholder svg {
            width: 24px;
            height: 24px;
        }
        .sponsor-block__body {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sponsor-block__name {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            outline: none;
            border-bottom: 1px solid transparent;
            padding-bottom: 2px;
            min-height: 1.5em;
        }
        .sponsor-block__name:focus {
            border-bottom-color: #3b82f6;
        }
        .sponsor-block__name:empty::before {
            content: attr(data-placeholder);
            color: #9ca3af;
            pointer-events: none;
        }
        .sponsor-block__url-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sponsor-block__url-row svg {
            width: 14px;
            height: 14px;
            color: #6b7280;
            flex-shrink: 0;
        }
        .sponsor-block__url {
            font-size: 13px;
            color: #6b7280;
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            min-width: 0;
            border-bottom: 1px solid transparent;
            padding-bottom: 1px;
        }
        .sponsor-block__url:focus {
            border-bottom-color: #3b82f6;
            color: #111827;
        }
        .sponsor-block__url::placeholder {
            color: #d1d5db;
        }
        .sponsor-block__logo-url-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }
        .sponsor-block__logo-url-row svg {
            width: 14px;
            height: 14px;
            color: #6b7280;
            flex-shrink: 0;
        }
        .sponsor-block__logo-url {
            font-size: 13px;
            color: #6b7280;
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            min-width: 0;
            border-bottom: 1px solid transparent;
            padding-bottom: 1px;
        }
        .sponsor-block__logo-url:focus {
            border-bottom-color: #3b82f6;
            color: #111827;
        }
        .sponsor-block__logo-url::placeholder {
            color: #d1d5db;
        }
    `;

    let styleInjected = false;
    function injectStyles() {
        if (styleInjected) return;
        const style = document.createElement('style');
        style.textContent = CSS;
        document.head.appendChild(style);
        styleInjected = true;
    }

    const LOGO_PLACEHOLDER_SVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" /></svg>`;
    const LINK_SVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>`;
    const IMAGE_SVG = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 21h18M3.75 3h16.5M4.5 3v18M19.5 3v18" /></svg>`;

    class SponsorBlock {
        static get toolbox() {
            return {
                title: 'Sponsor',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>`,
            };
        }

        static get isReadOnlySupported() {
            return true;
        }

        static get sanitize() {
            return {
                name: false,
                logoUrl: false,
                websiteUrl: false,
            };
        }

        constructor({ data, readOnly }) {
            injectStyles();
            this.data = {
                name: data.name || '',
                logoUrl: data.logoUrl || '',
                websiteUrl: data.websiteUrl || '',
            };
            this.readOnly = readOnly;
        }

        render() {
            const wrapper = document.createElement('div');
            wrapper.classList.add('sponsor-block');

            // Logo
            this._logoWrap = document.createElement('div');
            this._logoWrap.classList.add('sponsor-block__logo-wrap');
            this._renderLogo();
            wrapper.appendChild(this._logoWrap);

            // Body
            const body = document.createElement('div');
            body.classList.add('sponsor-block__body');

            // Name
            this._nameEl = document.createElement('div');
            this._nameEl.classList.add('sponsor-block__name');
            this._nameEl.setAttribute('data-placeholder', 'Sponsor name');
            this._nameEl.contentEditable = this.readOnly ? 'false' : 'true';
            this._nameEl.textContent = this.data.name;
            body.appendChild(this._nameEl);

            // Logo URL row
            const logoUrlRow = document.createElement('div');
            logoUrlRow.classList.add('sponsor-block__logo-url-row');
            logoUrlRow.innerHTML = IMAGE_SVG;
            this._logoUrlEl = document.createElement('input');
            this._logoUrlEl.type = 'url';
            this._logoUrlEl.classList.add('sponsor-block__logo-url');
            this._logoUrlEl.placeholder = 'Logo image URL';
            this._logoUrlEl.value = this.data.logoUrl;
            this._logoUrlEl.readOnly = this.readOnly;
            this._logoUrlEl.addEventListener('input', () => this._renderLogo());
            logoUrlRow.appendChild(this._logoUrlEl);
            body.appendChild(logoUrlRow);

            // Website URL row
            const urlRow = document.createElement('div');
            urlRow.classList.add('sponsor-block__url-row');
            urlRow.innerHTML = LINK_SVG;
            this._websiteUrlEl = document.createElement('input');
            this._websiteUrlEl.type = 'url';
            this._websiteUrlEl.classList.add('sponsor-block__url');
            this._websiteUrlEl.placeholder = 'Website URL';
            this._websiteUrlEl.value = this.data.websiteUrl;
            this._websiteUrlEl.readOnly = this.readOnly;
            urlRow.appendChild(this._websiteUrlEl);
            body.appendChild(urlRow);

            wrapper.appendChild(body);
            return wrapper;
        }

        _renderLogo() {
            const url = this._logoUrlEl ? this._logoUrlEl.value.trim() : this.data.logoUrl;
            this._logoWrap.innerHTML = '';
            if (url) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Sponsor logo';
                img.onerror = () => this._showLogoPlaceholder();
                this._logoWrap.appendChild(img);
            } else {
                this._showLogoPlaceholder();
            }
        }

        _showLogoPlaceholder() {
            this._logoWrap.innerHTML = `<div class="sponsor-block__logo-placeholder">${LOGO_PLACEHOLDER_SVG}<span>Logo URL</span></div>`;
        }

        save(blockContent) {
            return {
                name: this._nameEl.textContent.trim(),
                logoUrl: this._logoUrlEl.value.trim(),
                websiteUrl: this._websiteUrlEl.value.trim(),
            };
        }

        validate(savedData) {
            return savedData.name.trim().length > 0;
        }
    }

    return SponsorBlock;
}));
