/**
 * TextColorTool
 *
 * Inline tool that colors the selected text. Its toolbar button opens a menu
 * in EditorJS's own popover (an inline tool's render() returning a menu
 * config with `children`, EditorJS 2.30+) listing:
 * - the active theme's palette colors,
 * - "Custom…", the browser's native color picker,
 * - "Remove color".
 *
 * Colors come from `config.colorPalette`, the same `{ name, label, color }`
 * entries as the quote and callout tools (theme.json settings.color.palette,
 * injected as window.THEATRECMS_COLOR_PALETTE; see editorjs-config.js).
 *
 * Applying a color wraps the selection in `<span style="color: …">`, which
 * EditorJsHtmlConverter renders with the color validated. It doesn't use the
 * browser's foreColor command: when the selection is exactly a formatting
 * element's text (e.g. all of a <b>), that command styles the <b> itself, and
 * EditorJS's sanitizer then drops the style on save, losing the color.
 *
 * The selection is taken out of any colored span around it (splitting that
 * span) and stripped of colored spans inside it, then wrapped in a new span
 * (apply) or put back plain (remove), so colors never nest and only the
 * selected text changes.
 *
 * Usage:
 *   const editor = new EditorJS({
 *     tools: {
 *       textColor: {
 *         class: TextColorTool,
 *         config: { colorPalette: window.THEATRECMS_COLOR_PALETTE || [] },
 *       }
 *     }
 *   });
 */

class TextColorTool {
    // Used only when a theme declares no settings.color.palette in its theme.json.
    static get FALLBACK_COLOR_PALETTE() {
        return [
            { name: 'grey', label: 'Grey', color: '#94a3b8' },
            { name: 'blue', label: 'Blue', color: '#3b82f6' },
            { name: 'green', label: 'Green', color: '#22c55e' },
            { name: 'red', label: 'Red', color: '#ef4444' },
        ];
    }

    static get isInline() {
        return true;
    }

    static get title() {
        return 'Text color';
    }

    static get sanitize() {
        return {
            span: { style: true },
        };
    }

    constructor({ api, config }) {
        this.api = api;
        // The selection when the toolbar opened. The custom color input's native dialog
        // takes focus, so the color is applied to this copy rather than the live selection.
        this.range = null;

        const configured = (config && config.colorPalette) || [];
        this.palette = (configured.length > 0 ? configured : TextColorTool.FALLBACK_COLOR_PALETTE)
            .filter((entry) => entry && entry.color);
    }

    render() {
        const selection = window.getSelection();
        this.range = selection && selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null;

        const current = this.currentColor();

        return {
            icon: TextColorTool.icon(current),
            title: TextColorTool.title,
            isActive: current !== null,
            children: {
                items: [
                    ...this.palette.map((entry) => ({
                        icon: TextColorTool.swatch(entry.color),
                        title: entry.label || entry.name || entry.color,
                        isActive: current !== null && current === TextColorTool.normalize(entry.color),
                        onActivate: () => this.apply(entry.color),
                    })),
                    { type: 'html', element: this.renderCustomPicker(current) },
                    {
                        icon: TextColorTool.removeIcon,
                        title: 'Remove color',
                        isDisabled: current === null,
                        onActivate: () => this.remove(),
                    },
                ],
                // No onOpen/onClose with api.selection.setFakeBackground() (as EditorJS's
                // "Convert to" menu has): it rewrites the text, which EditorJS takes as a new
                // selection and closes this menu as soon as it opens.
            },
        };
    }

    renderCustomPicker(current) {
        const label = document.createElement('label');
        label.className = 'text-color-tool__custom';
        label.style.cssText = 'display:flex;align-items:center;gap:10px;padding:3px 8px;cursor:pointer;font-size:14px;';

        const input = document.createElement('input');
        input.type = 'color';
        input.value = current !== null ? TextColorTool.toHex(current) : '#000000';
        input.style.cssText = 'width:20px;height:20px;padding:0;border:0;background:none;cursor:pointer;';
        input.addEventListener('change', () => this.apply(input.value));

        label.append(input, document.createTextNode('Custom…'));

        return label;
    }

    apply(color) {
        this.recolor(color);
    }

    remove() {
        this.recolor(null);
    }

    /**
     * Sets the saved selection's text color, or removes it when color is null, and
     * selects the result.
     */
    recolor(color) {
        const range = this.range;
        if (!range || range.collapsed) {
            this.api.inlineToolbar.close();
            return;
        }

        const ancestor = this.coloredAncestor(range);
        let tail = null;

        if (ancestor) {
            // Split the colored span around the selection: it keeps the text before it,
            // `tail` (a copy of the span) gets the text after it.
            const after = document.createRange();
            after.setStart(range.endContainer, range.endOffset);
            after.setEnd(ancestor, ancestor.childNodes.length);
            tail = ancestor.cloneNode(false);
            tail.append(after.extractContents());
        }

        const selected = range.extractContents();
        selected.querySelectorAll('span').forEach((span) => TextColorTool.uncolor(span));

        let first;
        let last;
        let inserted;
        if (color !== null) {
            const span = document.createElement('span');
            span.style.color = color;
            span.append(selected);
            first = span;
            last = span;
            inserted = span;
        } else {
            first = selected.firstChild;
            last = selected.lastChild;
            inserted = selected;
        }

        if (ancestor) {
            ancestor.after(inserted, tail);
            [ancestor, tail].forEach((span) => {
                if (span.textContent === '') {
                    span.remove();
                }
            });
        } else {
            range.insertNode(inserted);
        }

        if (first && last && first.isConnected) {
            const result = document.createRange();
            if (color !== null) {
                result.selectNodeContents(first);
            } else {
                result.setStartBefore(first);
                result.setEndAfter(last);
            }
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(result);
        }

        this.api.inlineToolbar.close();
    }

    /** The selection's text color (normalized), or null when it isn't colored. */
    currentColor() {
        const span = this.range ? this.coloredAncestor(this.range) : null;

        return span ? TextColorTool.normalize(span.style.color) : null;
    }

    /**
     * The outermost colored span holding the whole range, within its block, or null.
     */
    coloredAncestor(range) {
        let node = range.commonAncestorContainer;
        let found = null;

        while (node && !(node.classList && (node.classList.contains('ce-block') || node.isContentEditable === false))) {
            if (node.nodeName === 'SPAN' && node.style && node.style.color) {
                found = node;
            }
            if (node.getAttribute && node.getAttribute('contenteditable') === 'true') {
                break;
            }
            node = node.parentNode;
        }

        return found;
    }

    /** Drops a span's color, unwrapping the span when nothing else is left on it. */
    static uncolor(span) {
        span.style.removeProperty('color');

        if (span.getAttribute('style') === '' || span.style.length === 0) {
            span.replaceWith(...span.childNodes);
        }
    }

    /** A color as the browser serializes it (e.g. "rgb(59, 130, 246)"), for comparisons. */
    static normalize(color) {
        const probe = document.createElement('span');
        probe.style.color = color;

        return probe.style.color || String(color);
    }

    static toHex(color) {
        const match = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(TextColorTool.normalize(color));
        if (!match) {
            return '#000000';
        }

        return '#' + match.slice(1, 4).map((part) => Number(part).toString(16).padStart(2, '0')).join('');
    }

    static escapeAttribute(value) {
        return String(value).replace(/[&"<>]/g, (char) => ({ '&': '&amp;', '"': '&quot;', '<': '&lt;', '>': '&gt;' })[char]);
    }

    static swatch(color) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
            + '<circle cx="10" cy="10" r="7" fill="' + TextColorTool.escapeAttribute(color) + '" stroke="rgba(0,0,0,0.15)"/>'
            + '</svg>';
    }

    /** An "A" over a bar in the current color (currentColor when none). */
    static icon(color) {
        const bar = color !== null ? TextColorTool.escapeAttribute(color) : 'currentColor';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
            + '<path d="M6.5 13.5 10 4.5l3.5 9M7.7 10.5h4.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
            + '<rect x="4.5" y="15.5" width="11" height="2.5" rx="1" fill="' + bar + '"/>'
            + '</svg>';
    }

    static get removeIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
            + '<circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            + '<path d="M5 15 15 5" stroke="currentColor" stroke-width="1.5"/>'
            + '</svg>';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = TextColorTool;
} else if (typeof define === 'function' && define.amd) {
    define([], () => TextColorTool);
} else {
    window.TextColorTool = TextColorTool;
}
