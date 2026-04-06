import Plugin from '@ckeditor/ckeditor5-core/src/plugin'
import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

/** U+201C LEFT DOUBLE QUOTATION MARK (“) */
const DOUBLE_QUOTE_OPEN_TYPO = '\u201C'
/** U+201D RIGHT DOUBLE QUOTATION MARK (”) */
const DOUBLE_QUOTE_CLOSE_TYPO = '\u201D'

export default class ReplaceQuotesWithGuillemets extends Plugin {
    static get pluginName() {
        return 'ReplaceQuotesWithGuillemets'
    }

    init() {
        const editor = this.editor

        editor.ui.componentFactory.add('replaceQuotesWithGuillemets', locale => {
            const buttonView = new ButtonView(locale)

            buttonView.set({
                label: 'A(«»)',
                withText: true,
                tooltip: 'Заменить "..." / “...” на «...»',
            })

            buttonView.on('execute', () => {
                const hasSelection = !editor.model.document.selection.isCollapsed

                if (hasSelection) {
                    this._replaceStraightQuotesInSelection()
                    return
                }

                const currentData = editor.getData()
                const updatedData = this._replaceStraightQuotesInHtml(currentData)

                if (updatedData !== currentData) {
                    editor.setData(updatedData)
                }
            })

            return buttonView
        })
    }

    _replaceStraightQuotesInHtml(html) {
        const parser = new DOMParser()
        const doc = parser.parseFromString(`<div data-root="1">${html}</div>`, 'text/html')
        const root = doc.querySelector('[data-root="1"]')

        if (!root) {
            return html
        }

        this._replaceStraightQuotesByPairsAcrossNodes(doc, root)

        return root.innerHTML
    }

    _replaceStraightQuotesInSelection() {
        const editor = this.editor
        const selection = editor.model.document.selection
        const ranges = Array.from(selection.getRanges())
        const replacements = []

        for (const range of ranges) {
            const segments = this._collectEligibleSelectionTextSegments(range)

            if (segments.length === 0) {
                continue
            }

            const updatedTextsBySegmentIndex = this._buildUpdatedTextsForSegments(segments)

            for (let i = 0; i < segments.length; i++) {
                const originalText = segments[i].text
                const updatedText = updatedTextsBySegmentIndex[i]

                if (typeof updatedText !== 'string' || updatedText === originalText) {
                    continue
                }

                replacements.push({
                    parent: segments[i].parent,
                    startOffset: segments[i].startOffset,
                    endOffset: segments[i].endOffset,
                    attributes: segments[i].attributes,
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

    _replaceStraightQuotesByPairsAcrossNodes(doc, root) {
        const textNodes = this._collectEligibleTextNodes(doc, root)

        if (textNodes.length === 0) {
            return
        }

        const nodeToChars = new Map()
        const flatChars = []

        for (const node of textNodes) {
            const chars = Array.from(node.nodeValue || '')
            nodeToChars.set(node, chars)

            for (let i = 0; i < chars.length; i++) {
                flatChars.push({
                    char: chars[i],
                    node,
                    offset: i,
                })
            }
        }

        this._replaceFlatCharsQuotesByPairs(flatChars, (node, offset, replacement) => {
            this._setNodeChar(nodeToChars, node, offset, replacement)
        })

        for (const [node, chars] of nodeToChars.entries()) {
            node.nodeValue = chars.join('')
        }
    }

    _collectEligibleTextNodes(doc, root) {
        const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT)
        const nodes = []
        let currentNode = walker.nextNode()

        while (currentNode) {
            const parentElement = currentNode.parentElement
            const isInsideCode = parentElement && parentElement.closest('code, pre, kbd, samp')

            if (!isInsideCode) {
                nodes.push(currentNode)
            }

            currentNode = walker.nextNode()
        }

        return nodes
    }

    _collectEligibleSelectionTextSegments(range) {
        const segments = []

        for (const item of range.getItems()) {
            if (!item.is('$textProxy')) {
                continue
            }

            if (item.hasAttribute('code')) {
                continue
            }

            segments.push({
                parent: item.parent,
                startOffset: item.startOffset,
                endOffset: item.endOffset,
                text: item.data,
                attributes: Object.fromEntries(item.getAttributes()),
            })
        }

        return segments
    }

    _buildUpdatedTextsForSegments(segments) {
        const segmentToChars = new Map()
        const flatChars = []

        for (let segmentIndex = 0; segmentIndex < segments.length; segmentIndex++) {
            const chars = Array.from(segments[segmentIndex].text || '')
            segmentToChars.set(segmentIndex, chars)

            for (let i = 0; i < chars.length; i++) {
                flatChars.push({
                    char: chars[i],
                    node: segmentIndex,
                    offset: i,
                })
            }
        }

        this._replaceFlatCharsQuotesByPairs(flatChars, (segmentIndex, offset, replacement) => {
            const chars = segmentToChars.get(segmentIndex)

            if (!chars || offset < 0 || offset >= chars.length) {
                return
            }

            chars[offset] = replacement
        })

        const updatedTexts = []

        for (let segmentIndex = 0; segmentIndex < segments.length; segmentIndex++) {
            const chars = segmentToChars.get(segmentIndex) || []
            updatedTexts.push(chars.join(''))
        }

        return updatedTexts
    }

    _replaceFlatCharsQuotesByPairs(flatChars, applyReplacement) {
        const openQuoteStack = []

        for (let i = 0; i < flatChars.length; i++) {
            const current = flatChars[i]

            if (current.char === DOUBLE_QUOTE_OPEN_TYPO) {
                openQuoteStack.push(current)
                continue
            }

            if (current.char === DOUBLE_QUOTE_CLOSE_TYPO) {
                if (openQuoteStack.length > 0) {
                    const openQuoteRef = openQuoteStack.pop()
                    applyReplacement(openQuoteRef.node, openQuoteRef.offset, '«')
                    applyReplacement(current.node, current.offset, '»')
                }
                continue
            }

            if (current.char !== '"') {
                continue
            }

            const isLeftBoundary = this._isLeftBoundaryInFlatChars(flatChars, i)
            const isRightBoundary = this._isRightBoundaryInFlatChars(flatChars, i)

            if (!isLeftBoundary && !isRightBoundary) {
                continue
            }

            // Prefer opening if we don't have anything to close yet.
            if (isLeftBoundary && (!isRightBoundary || openQuoteStack.length === 0)) {
                openQuoteStack.push(current)
                continue
            }

            if (isRightBoundary && openQuoteStack.length > 0) {
                const openQuoteRef = openQuoteStack.pop()
                applyReplacement(openQuoteRef.node, openQuoteRef.offset, '«')
                applyReplacement(current.node, current.offset, '»')
                continue
            }

            if (isLeftBoundary) {
                openQuoteStack.push(current)
            }
        }
    }

    _setNodeChar(nodeToChars, node, offset, replacement) {
        const chars = nodeToChars.get(node)

        if (!chars || offset < 0 || offset >= chars.length) {
            return
        }

        chars[offset] = replacement
    }

    _isLeftBoundaryInFlatChars(flatChars, index) {
        if (index === 0) {
            return true
        }

        // New text node means a visual boundary (e.g. after <br> or block split).
        if (flatChars[index - 1].node !== flatChars[index].node) {
            return true
        }

        return /\s/u.test(flatChars[index - 1].char)
    }

    _isRightBoundaryInFlatChars(flatChars, index) {
        if (index === flatChars.length - 1) {
            return true
        }

        // Node transition is also a valid right boundary.
        if (flatChars[index + 1].node !== flatChars[index].node) {
            return true
        }

        return /\s/u.test(flatChars[index + 1].char)
    }
}
