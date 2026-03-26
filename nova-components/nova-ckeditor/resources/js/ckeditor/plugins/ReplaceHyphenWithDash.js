import Plugin from '@ckeditor/ckeditor5-core/src/plugin'
import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

export default class ReplaceHyphenWithDash extends Plugin {
    static get pluginName() {
        return 'ReplaceHyphenWithDash'
    }

    init() {
        const editor = this.editor

        editor.ui.componentFactory.add('replaceHyphenWithDash', locale => {
            const buttonView = new ButtonView(locale)

            buttonView.set({
                label: 'A(—)',
                withText: true,
                tooltip: 'Заменить дефис на тире в тексте',
            })

            buttonView.on('execute', () => {
                const hasSelection = !editor.model.document.selection.isCollapsed

                if (hasSelection) {
                    this._replaceHyphenInSelection()
                    return
                }

                const currentData = editor.getData()
                const updatedData = this._replaceHyphenInHtml(currentData)

                if (updatedData !== currentData) {
                    editor.setData(updatedData)
                }
            })

            return buttonView
        })
    }

    _replaceHyphenInHtml(html) {
        const parser = new DOMParser()
        const doc = parser.parseFromString(`<div data-root="1">${html}</div>`, 'text/html')
        const root = doc.querySelector('[data-root="1"]')

        if (!root) {
            return html
        }

        const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT)
        let currentNode = walker.nextNode()

        while (currentNode) {
            const parentElement = currentNode.parentElement
            const isInsideCode = parentElement && parentElement.closest('code, pre, kbd, samp')

            if (!isInsideCode) {
                currentNode.nodeValue = this._replaceHyphenSmart(currentNode.nodeValue || '')
            }

            currentNode = walker.nextNode()
        }

        return root.innerHTML
    }

    _replaceHyphenInSelection() {
        const editor = this.editor
        const selection = editor.model.document.selection
        const ranges = Array.from(selection.getRanges())
        const replacements = []

        for (const range of ranges) {
            for (const item of range.getItems()) {
                if (!item.is('$textProxy')) {
                    continue
                }

                if (item.hasAttribute('code')) {
                    continue
                }

                const originalText = item.data || ''
                const updatedText = this._replaceHyphenSmart(originalText)

                if (updatedText === originalText) {
                    continue
                }

                replacements.push({
                    parent: item.parent,
                    startOffset: item.startOffset,
                    endOffset: item.endOffset,
                    attributes: Object.fromEntries(item.getAttributes()),
                    text: updatedText,
                })
            }
        }

        if (replacements.length === 0) {
            return
        }

        editor.model.change(writer => {
            for (let i = replacements.length - 1; i >= 0; i--) {
                const replacement = replacements[i]
                const start = writer.createPositionAt(replacement.parent, replacement.startOffset)
                const end = writer.createPositionAt(replacement.parent, replacement.endOffset)
                const replaceRange = writer.createRange(start, end)

                writer.remove(replaceRange)
                writer.insertText(replacement.text, replacement.attributes, start)
            }
        })
    }

    _replaceHyphenSmart(text) {
        const chars = Array.from(text)

        for (let i = 0; i < chars.length; i++) {
            if (chars[i] !== '-') {
                continue
            }

            const prev = chars[i - 1] || ''
            const next = chars[i + 1] || ''
            const prevNonSpaceIndex = this._findPrevNonSpaceIndex(chars, i - 1)
            const nextNonSpaceIndex = this._findNextNonSpaceIndex(chars, i + 1)
            const prevNonSpace = prevNonSpaceIndex >= 0 ? chars[prevNonSpaceIndex] : ''
            const nextNonSpace = nextNonSpaceIndex >= 0 ? chars[nextNonSpaceIndex] : ''

            // Do not replace in arithmetic context (e.g. 5 - 2 = 3).
            if (this._isMathContext(chars, i)) {
                continue
            }

            // Convert range-like forms: 1590-1600, 1590 - 1600, 15- 17, 15 -17
            const shouldReplace =
                (this._isDigit(prevNonSpace) && this._isDigit(nextNonSpace)) ||
                (this._isWhitespace(prev) && this._isWhitespace(next))

            if (shouldReplace) {
                chars[i] = '—'
            }
        }

        return chars.join('')
    }

    _isDigit(char) {
        return /\d/u.test(char)
    }

    _isWhitespace(char) {
        return /\s/u.test(char)
    }

    _findPrevNonSpaceIndex(chars, startIndex) {
        for (let i = startIndex; i >= 0; i--) {
            if (!this._isWhitespace(chars[i])) {
                return i
            }
        }

        return -1
    }

    _findNextNonSpaceIndex(chars, startIndex) {
        for (let i = startIndex; i < chars.length; i++) {
            if (!this._isWhitespace(chars[i])) {
                return i
            }
        }

        return -1
    }

    _isMathContext(chars, hyphenIndex) {
        // Check line/segment around hyphen; if expression contains math signs,
        // we keep minus as-is.
        let left = hyphenIndex
        let right = hyphenIndex

        while (left > 0 && chars[left - 1] !== '\n') {
            left--
        }

        while (right < chars.length - 1 && chars[right + 1] !== '\n') {
            right++
        }

        const segment = chars.slice(left, right + 1).join('')
        return /[=+*/]/u.test(segment)
    }
}
