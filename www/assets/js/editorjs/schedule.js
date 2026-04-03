/**
 * ScheduleBlock Plugin for Editor.js
 *
 * A zero-data placeholder block that marks where the production event schedule
 * should be rendered. The block stores no editable content — it is a positional
 * token. The server-side EditorJsHtmlConverter emits
 * <div class="editorjs-schedule-block"></div> which the theme replaces with the
 * actual list of events attached to the production.
 */

class ScheduleBlock {
    static get toolbox() {
        return {
            title: 'Schedule',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
        <line x1="8" y1="14" x2="8" y2="14"/>
        <line x1="12" y1="14" x2="12" y2="14"/>
        <line x1="16" y1="14" x2="16" y2="14"/>
        <line x1="8" y1="18" x2="8" y2="18"/>
        <line x1="12" y1="18" x2="12" y2="18"/>
      </svg>`,
        };
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
    }

    render() {
        ScheduleBlock._injectStyles();

        const wrapper = document.createElement('div');
        wrapper.classList.add('ce-schedule-block');

        const icon = document.createElement('span');
        icon.classList.add('ce-schedule-block__icon');
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
      <line x1="16" y1="2" x2="16" y2="6"/>
      <line x1="8" y1="2" x2="8" y2="6"/>
      <line x1="3" y1="10" x2="21" y2="10"/>
      <line x1="8" y1="14" x2="8" y2="14"/>
      <line x1="12" y1="14" x2="12" y2="14"/>
      <line x1="16" y1="14" x2="16" y2="14"/>
      <line x1="8" y1="18" x2="8" y2="18"/>
      <line x1="12" y1="18" x2="12" y2="18"/>
    </svg>`;

        const text = document.createElement('div');
        text.classList.add('ce-schedule-block__text');

        const heading = document.createElement('p');
        heading.classList.add('ce-schedule-block__heading');
        heading.textContent = 'Schedule Block';

        const sub = document.createElement('p');
        sub.classList.add('ce-schedule-block__sub');
        sub.textContent = 'Production event schedule will appear here.';

        text.appendChild(heading);
        text.appendChild(sub);
        wrapper.appendChild(icon);
        wrapper.appendChild(text);

        return wrapper;
    }

    save() {
        return {};
    }

    validate() {
        return true;
    }

    static _injectStyles() {
        if (document.getElementById('ce-schedule-block-styles')) return;
        const style = document.createElement('style');
        style.id = 'ce-schedule-block-styles';
        style.textContent = `
      .ce-schedule-block {
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
      .ce-schedule-block__icon {
        flex-shrink: 0;
        opacity: 0.5;
      }
      .ce-schedule-block__heading {
        margin: 0 0 2px;
        font-weight: 600;
        font-size: 0.875rem;
        color: #374151;
      }
      .ce-schedule-block__sub {
        margin: 0;
        font-size: 0.78rem;
        color: #9ca3af;
      }
    `;
        document.head.appendChild(style);
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ScheduleBlock;
} else if (typeof define === 'function' && define.amd) {
    define([], () => ScheduleBlock);
} else {
    window.ScheduleBlock = ScheduleBlock;
}
