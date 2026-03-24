import CkEditor from '../../ckeditor/ckeditor'

export const TERMIN_DESCRIPTION_TOOLBAR = 'toolbar-theins-mini'

const defaultTextPartLanguage = [
    { title: 'Farsi', languageCode: 'fa' },
    { title: 'English', languageCode: 'en' },
]

function stringToRegex(string) {
    if (typeof string === 'string' && string.startsWith('/') && string.endsWith('/')) {
        const regexPattern = string.slice(1, -1)

        return new RegExp(regexPattern)
    }

    return string
}

function normalizeHtmlSupportItems(htmlSupport) {
    if (htmlSupport) {
        if (htmlSupport.allow) {
            htmlSupport.allow.map((item) => {
                if (item.name) {
                    item.name = stringToRegex(item.name)
                }

                return item
            })
        } else {
            htmlSupport.allow = []
        }

        if (htmlSupport.disallow) {
            htmlSupport.disallow.map((item) => {
                if (item.name) {
                    item.name = stringToRegex(item.name)
                }

                return item
            })
        } else {
            htmlSupport.disallow = []
        }

        return htmlSupport
    }

    return {
        allow: [],
        disallow: [],
    }
}

/**
 * Same shape as form-ckeditor uses, sourced from config('nova-ckeditor.toolbars.*') via Nova.config('ckeditor').
 */
export function buildTerminDescriptionCkConfig(fieldKey) {
    const ck = typeof Nova !== 'undefined' && Nova.config ? Nova.config('ckeditor') : {}
    const toolbar = ck.toolbars?.[TERMIN_DESCRIPTION_TOOLBAR]

    if (!toolbar?.items) {
        throw new Error(
            `[nova-ckeditor] Missing toolbars.${TERMIN_DESCRIPTION_TOOLBAR} in Nova.config('ckeditor'). Publish or sync config/nova-ckeditor.php.`,
        )
    }

    const toolbarOptions = { ...(toolbar.options || {}) }
    const headings = toolbarOptions.headings
    const image = toolbarOptions.image ?? CkEditor.defaultConfig.image

    delete toolbarOptions.headings
    delete toolbarOptions.image

    const config = {
        attribute: `termin-picker-desc-${fieldKey}`,
        mediaBrowser: true,
        snippetBrowser: [],
        htmlSupport: normalizeHtmlSupportItems(toolbar['html-support']),
        isReadOnly: false,
        image,
        language: {
            ui: toolbar['ui-language']?.name ?? 'en',
            content: toolbar['content-lang'],
            textPartLanguage: toolbar['text-part-language'] ?? defaultTextPartLanguage,
        },
        heading: {
            options: headings,
        },
        toolbar: {
            items: toolbar.items,
            shouldNotGroupWhenFull: toolbar['should-not-group-when-full'],
        },
        simpleUpload: {
            ...CkEditor.defaultConfig.simpleUpload,
            headers: {
                ...CkEditor.defaultConfig.simpleUpload.headers,
                'X-Toolbar': TERMIN_DESCRIPTION_TOOLBAR,
            },
        },
        ...toolbarOptions,
    }

    return {
        toolbar,
        config,
    }
}
