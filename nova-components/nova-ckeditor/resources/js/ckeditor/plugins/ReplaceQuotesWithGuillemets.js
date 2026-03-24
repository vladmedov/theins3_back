import Plugin from '@ckeditor/ckeditor5-core/src/plugin'
import ButtonView from '@ckeditor/ckeditor5-ui/src/button/buttonview'

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
                tooltip: 'Заменить "..." на «...»',
            })

            buttonView.on('execute', () => {
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

        const openQuoteStack = []

        for (let i = 0; i < flatChars.length; i++) {
            const current = flatChars[i]

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
                this._setNodeChar(nodeToChars, openQuoteRef.node, openQuoteRef.offset, '«')
                this._setNodeChar(nodeToChars, current.node, current.offset, '»')
                continue
            }

            if (isLeftBoundary) {
                openQuoteStack.push(current)
            }
        }

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

        return /\s/u.test(flatChars[index - 1].char)
    }

    _isRightBoundaryInFlatChars(flatChars, index) {
        if (index === flatChars.length - 1) {
            return true
        }

        return /\s/u.test(flatChars[index + 1].char)
    }
}
