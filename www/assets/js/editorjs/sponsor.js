/**
 * SponsorBlock Plugin for Editor.js
 *
 * A zero-data placeholder block that marks where production sponsors
 * should be rendered. The block stores no editable content — it is a
 * positional token. The server-side EditorJsHtmlConverter emits
 * <div class="editorjs-sponsor-block"></div> which the theme replaces
 * with the actual sponsor list.
 */

class SponsorBlock {
    static get toolbox() {
        return {
            title: 'Sponsors',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
        <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
        <path d="M4 22h16"/>
        <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
        <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
        <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
      </svg>`,
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this._wrapper = null;
    }

    render() {
        SponsorBlock._injectStyles();

        const wrapper = document.createElement('div');
        wrapper.classList.add('ce-sponsor-block');

        const icon = document.createElement('span');
        icon.classList.add('ce-sponsor-block__icon');
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
      <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
      <path d="M4 22h16"/>
      <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
      <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
      <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
    </svg>`;

        const text = document.createElement('div');
        text.classList.add('ce-sponsor-block__text');

        const heading = document.createElement('p');
        heading.classList.add('ce-sponsor-block__heading');
        heading.textContent = 'Sponsor Block';

        const sub = document.createElement('p');
        sub.classList.add('ce-sponsor-block__sub');
        sub.textContent = 'Production sponsors will appear here.';

        text.appendChild(heading);
        text.appendChild(sub);
        wrapper.appendChild(icon);
        wrapper.appendChild(text);

        this._wrapper = wrapper;
        return wrapper;
    }

    save() {
        return {};
    }

    validate() {
        return true;
    }

    static _injectStyles() {
        if (document.getElementById('ce-sponsor-block-styles')) return;
        const style = document.createElement('style');
        style.id = 'ce-sponsor-block-styles';
        style.textContent = `
      .ce-sponsor-block {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 18px;
        border: 2px dashed #d1d5db;
        border-radius: 10px;
        background: #f9fafb;
        color: #6b7280;
        user-select: none;
        cursor: default;
      }
      .ce-sponsor-block__icon {
        flex-shrink: 0;
        opacity: 0.5;
      }
      .ce-sponsor-block__heading {
        margin: 0 0 2px;
        font-weight: 600;
        font-size: 0.875rem;
        color: #374151;
      }
      .ce-sponsor-block__sub {
        margin: 0;
        font-size: 0.78rem;
        color: #9ca3af;
      }
    `;
        document.head.appendChild(style);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SponsorBlock;
} else if (typeof define === 'function' && define.amd) {
    define([], () => SponsorBlock);
} else {
    window.SponsorBlock = SponsorBlock;
}
