/**
 * Cite
 *
 * Inline tool that wraps the selected text fragment in a semantic HTML
 * `<cite>` element, for referencing the title of a creative work (a play,
 * production, book, film, album, etc.) per the HTML spec's definition of
 * the tag: https://html.spec.whatwg.org/multipage/text-level-semantics.html#the-cite-element
 *
 * Modeled directly on the vendored @editorjs/underline tool (see
 * underline.js): a minimal isInline tool that surrounds/unwraps the
 * current selection with its tag via the EditorJS selection API.
 *
 * Usage:
 *   const editor = new EditorJS({
 *     tools: {
 *       cite: { class: Cite }
 *     }
 *   });
 */

class Cite {
    static get isInline() {
        return true;
    }

    static get title() {
        return 'Cite';
    }

    static get sanitize() {
        return {
            cite: {
                class: Cite.CSS,
            },
        };
    }

    static get CSS() {
        return 'cdx-cite';
    }

    constructor({ api }) {
        this.api = api;
        this.tag = 'CITE';
        this.button = null;
        this.iconClasses = {
            base: this.api.styles.inlineToolButton,
            active: this.api.styles.inlineToolButtonActive,
        };
    }

    render() {
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.classList.add(this.iconClasses.base);
        this.button.innerHTML = this.toolboxIcon;
        return this.button;
    }

    surround(range) {
        if (!range) {
            return;
        }

        const termWrapper = this.api.selection.findParentTag(this.tag, Cite.CSS);

        if (termWrapper) {
            this.unwrap(termWrapper);
        } else {
            this.wrap(range);
        }
    }

    wrap(range) {
        const cite = document.createElement(this.tag);
        cite.classList.add(Cite.CSS);
        cite.appendChild(range.extractContents());
        range.insertNode(cite);

        this.api.selection.expandToTag(cite);
    }

    unwrap(termWrapper) {
        this.api.selection.expandToTag(termWrapper);

        const selection = window.getSelection();
        if (!selection) {
            return;
        }

        const range = selection.getRangeAt(0);
        if (!range) {
            return;
        }

        const unwrappedContent = range.extractContents();

        termWrapper.parentNode?.removeChild(termWrapper);
        range.insertNode(unwrappedContent);

        selection.removeAllRanges();
        selection.addRange(range);
    }

    checkState() {
        const termTag = this.api.selection.findParentTag(this.tag, Cite.CSS);
        this.button?.classList.toggle(this.iconClasses.active, !!termTag);
        return !!termTag;
    }

    get toolboxIcon() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">'
            + '<text x="2" y="16" font-size="16" font-family="Georgia, \'Times New Roman\', serif" fill="currentColor">&#8221;</text>'
            + '</svg>';
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = Cite;
} else if (typeof define === 'function' && define.amd) {
    define([], () => Cite);
} else {
    window.Cite = Cite;
}
