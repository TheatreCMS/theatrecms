const INLINE_TOOLBAR_ITEMS = ['bold', 'italic', 'underline', 'link'];

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

export function buildEditorJsConfig({ holder, data, imageEndpoints } = {}) {
    if (!holder) {
        throw new Error('EditorJS config requires a holder ID.');
    }

    const paragraphConfig = {
        inlineToolbar: INLINE_TOOLBAR_ITEMS,
        config: {
            preserveBlank: true,
        },
    };

    const tools = {
        paragraph: paragraphConfig,
        quote: {
            class: Quote,
            inlineToolbar: true,
        },
        underline: {
            class: Underline,
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
                    quote: Quote,
                    paragraph: paragraphConfig,
                },
                EditorJsLibrary: EditorJS //ref EditorJS - This means only one global thing
            }
        },
        image: createImageTool(imageEndpoints),
        table: {
            class: Table,
            inlineToolbar: true,
        },
        callout: {
            class: Callout,
            inlineToolbar: INLINE_TOOLBAR_ITEMS,
        },
        sponsorBlock: {
            class: SponsorBlock,
        },
        textVariant: {
            class: TextVariantTune,
        },
    };

    const config = {
        holder,
        tools,
    };

    if (data !== undefined && data !== null) {
        config.data = data;
    }

    return config;
}
