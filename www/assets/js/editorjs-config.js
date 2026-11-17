const INLINE_TOOLBAR_ITEMS = ['bold', 'italic', 'underline', 'link', 'cite'];

const createImageTool = (imageEndpoints) => {
    const tool = {
        class: ImageTool,
    };

    if (imageEndpoints && (imageEndpoints.byFile || imageEndpoints.byUrl)) {
        tool.config = {
            endpoints: {
                byFile: imageEndpoints.byFile,
                byUrl: imageEndpoints.byUrl,
            },
        };
    }

    return tool;
};

export function buildEditorJsConfig({ holder, data, placeholder, imageEndpoints } = {}) {
    if (!holder) {
        throw new Error('EditorJS config requires a holder ID.');
    }

    const paragraphConfig = {
        inlineToolbar: INLINE_TOOLBAR_ITEMS,
        config: {
            preserveBlank: true,
        },
    };

    const colorPaletteConfig = {
        colorPalette: window.THEATRECMS_COLOR_PALETTE || [],
    };

    const tools = {
        paragraph: paragraphConfig,
        quote: {
            class: QuoteColorScheme,
            inlineToolbar: true,
            config: colorPaletteConfig,
        },
        underline: {
            class: Underline,
        },
        cite: {
            class: Cite,
        },
        header: {
            class: Header,
            inlineToolbar: true,
        },
        list: {
            class: EditorjsList,
            inlineToolbar: true,
        },
        columns: {
            class: editorjsColumns,
            config: {
                tools: {
                    header: Header,
                    list: EditorjsList,
                    quote: { class: QuoteColorScheme, config: colorPaletteConfig },
                    paragraph: paragraphConfig,
                },
                EditorJsLibrary: EditorJS //ref EditorJS - This means only one global thing
            }
        },
        image: createImageTool(imageEndpoints),
        linkTool: {
            class: LinkTool,
            config: {
                endpoint: '/admin/link-preview/fetch',
            },
        },
        table: {
            class: Table,
            inlineToolbar: true,
        },
        callout: {
            class: Callout,
            inlineToolbar: INLINE_TOOLBAR_ITEMS,
            config: colorPaletteConfig,
        },
        sponsorBlock: {
            class: SponsorBlock,
        },
        ctaCard: {
            class: CtaCard,
            inlineToolbar: INLINE_TOOLBAR_ITEMS,
        },
        scheduleBlock: {
            class: ScheduleBlock,
        },
        imageGallery: {
            class: ImageGallery,
            config: {
                uploadEndpoint: '/admin/images/upload',
            },
        },
        carousel: {
            class: Carousel,
            config: {
                uploadEndpoint: '/admin/images/upload',
            },
        },
        textVariant: {
            class: TextVariantTune,
        },
    };

    // Tell the form holding this editor that its content changed, for the sticky edit
    // header's unsaved-changes tracking (edit-header.js). The editor's content only reaches
    // a form field on save, so the form can't see these edits itself. Changes made while the
    // editor renders its initial data are ignored.
    let ready = false;
    const config = {
        holder,
        tools,
        onReady: () => {
            ready = true;
        },
        onChange: () => {
            if (ready) {
                document.getElementById(holder)?.closest('form')?.dispatchEvent(new Event('edit-form:dirty'));
            }
        },
    };

    if (data !== undefined && data !== null) {
        config.data = data;
    }

    if (placeholder !== undefined && placeholder !== null) {
        config.placeholder = placeholder;
    }

    return config;
}
