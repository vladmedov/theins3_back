/**
 * Paste sanitizer for CKEditor:
 * - Google Docs uses `<span style="font-weight:700">` / `font-style:italic` — promote those to `<strong>` / `<i>`
 * - unwrap remaining `<span>` (colour/noise only)
 * - normalize `<b>`→`<strong>`, `<em>`→`<i>`
 * - inside `<a>`: plain text only; edge spaces outside the link
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

    // 1) Process innermost <span> first (nested Docs markup).
    let span = findInnermostSpan(container)
    while (span) {
        promoteOrUnwrapStyledSpan(span)
        span = findInnermostSpan(container)
    }

    // 2) Normalize to project tags: <strong> for bold, <i> for italic.
    container.querySelectorAll('b').forEach((b) => {
        const strong = document.createElement('strong')
        while (b.firstChild) {
            strong.appendChild(b.firstChild)
        }
        b.replaceWith(strong)
    })

    container.querySelectorAll('em').forEach((em) => {
        const i = document.createElement('i')
        while (em.firstChild) {
            i.appendChild(em.firstChild)
        }
        em.replaceWith(i)
    })

    // 3) Links: only text inside, whitespace outside.
    container.querySelectorAll('a').forEach((a) => {
        const text = a.textContent ?? ''

        const leading = text.match(/^\s+/)?.[0] ?? ''
        const trailing = text.match(/\s+$/)?.[0] ?? ''
        const core = text.slice(leading.length, text.length - trailing.length)

        a.textContent = core

        if (leading) {
            a.parentNode.insertBefore(document.createTextNode(leading), a)
        }

        if (trailing) {
            a.parentNode.insertBefore(document.createTextNode(trailing), a.nextSibling)
        }
    })

    return container.innerHTML
}

/**
 * @param {HTMLElement} root
 * @returns {HTMLSpanElement|null}
 */
function findInnermostSpan(root) {
    const spans = root.querySelectorAll('span')
    for (const s of spans) {
        if (!s.querySelector('span')) {
            return s
        }
    }
    return null
}

/**
 * @param {string} styleStr
 * @returns {Record<string, string>}
 */
function parseStyleAttribute(styleStr) {
    const out = {}
    if (!styleStr) {
        return out
    }
    styleStr.split(';').forEach((part) => {
        const idx = part.indexOf(':')
        if (idx === -1) {
            return
        }
        const key = part.slice(0, idx).trim().toLowerCase()
        const val = part.slice(idx + 1).trim()
        if (key) {
            out[key] = val
        }
    })
    return out
}

/**
 * @param {HTMLSpanElement} span
 * @returns {{ bold: boolean, italic: boolean }}
 */
function readDocsSpanEmphasis(span) {
    const attr = span.getAttribute('style') || ''
    const map = parseStyleAttribute(attr)

    // Parsed DOM may also expose shorthand on .style
    if (span.style.fontWeight) {
        map['font-weight'] = span.style.fontWeight
    }
    if (span.style.fontStyle) {
        map['font-style'] = span.style.fontStyle
    }

    const fw = map['font-weight'] || ''
    let bold = false
    if (fw) {
        const n = parseInt(fw, 10)
        if (!Number.isNaN(n)) {
            bold = n >= 600
        } else {
            bold = /bold|bolder|700|800|900/i.test(fw)
        }
    }

    // font: 700 11pt Arial …
    if (!bold && /(?:^|;)\s*font\s*:/i.test(attr)) {
        const m = attr.match(/font\s*:\s*([^;]+)/i)
        if (m) {
            const first = m[1].trim().split(/\s+/)[0]
            const n = parseInt(first, 10)
            if (!Number.isNaN(n) && n >= 600) {
                bold = true
            } else if (/^bold$/i.test(first)) {
                bold = true
            }
        }
    }

    const fs = (map['font-style'] || '').trim().toLowerCase()
    const italic = fs === 'italic'

    return {bold, italic}
}

/**
 * @param {HTMLSpanElement} span
 */
function promoteOrUnwrapStyledSpan(span) {
    const parent = span.parentNode
    if (!parent) {
        return
    }

    const {bold, italic} = readDocsSpanEmphasis(span)

    if (!bold && !italic) {
        while (span.firstChild) {
            parent.insertBefore(span.firstChild, span)
        }
        parent.removeChild(span)
        return
    }

    if (bold && italic) {
        const iEl = document.createElement('i')
        while (span.firstChild) {
            iEl.appendChild(span.firstChild)
        }
        const sEl = document.createElement('strong')
        sEl.appendChild(iEl)
        parent.insertBefore(sEl, span)
        parent.removeChild(span)
        return
    }

    if (bold) {
        const sEl = document.createElement('strong')
        while (span.firstChild) {
            sEl.appendChild(span.firstChild)
        }
        parent.insertBefore(sEl, span)
        parent.removeChild(span)
        return
    }

    const iEl = document.createElement('i')
    while (span.firstChild) {
        iEl.appendChild(span.firstChild)
    }
    parent.insertBefore(iEl, span)
    parent.removeChild(span)
}
