/**
 * Unwraps every `<span>` in pasted HTML (typical Google Docs noise). Text and
 * nested elements (e.g. links) are preserved; only the span wrappers are removed.
 *
 * @param {string} html
 * @returns {string}
 */
export function sanitizePastedHtml(html) {
    if (!html || typeof html !== 'string') {
        return html
    }

    const container = document.createElement('div')
    container.innerHTML = html

    let span = container.querySelector('span')
    while (span) {
        const parent = span.parentNode
        while (span.firstChild) {
            parent.insertBefore(span.firstChild, span)
        }
        parent.removeChild(span)
        span = container.querySelector('span')
    }

    return container.innerHTML
}
