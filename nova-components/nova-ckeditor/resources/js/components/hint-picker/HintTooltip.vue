<template>
    <teleport to="body">
        <div
            v-if="isVisible"
            class="ck-hint-tooltip"
            :style="style"
            @mousedown.stop
        >
            <div class="ck-hint-tooltip__name">{{ titleText }}</div>
            <div v-if="shortDesc" class="ck-hint-tooltip__desc" v-html="shortDesc"></div>
            <button
                type="button"
                class="ck-hint-tooltip__edit"
                @mousedown.prevent
                @click="edit"
            >
                Редактировать
            </button>
        </div>
    </teleport>
</template>

<script>
export default {
    name: 'HintTooltip',

    props: {
        fieldKey: { type: String, required: true },
    },

    data() {
        return {
            isVisible: false,
            rect: null,
            hintHtml: '',
            displayText: '',
            _docHandler: null,
        }
    },

    computed: {
        style() {
            if (!this.rect) return {}
            return {
                top: this.rect.top - 16 + 'px',
                left: this.rect.left + this.rect.width / 2 + 'px',
            }
        },
        titleText() {
            const t = (this.displayText || '').trim()
            return t || 'ПОДСКАЗКА'
        },
        shortDesc() {
            const raw = this.hintHtml || ''
            if (!raw) return ''

            const div = document.createElement('div')
            div.innerHTML = raw
            const text = (div.textContent || div.innerText || '').trim()

            if (text.length <= 160) return raw

            const truncated = text.slice(0, 160).replace(/\s+\S*$/, '') + '…'
            return `<p>${truncated}</p>`
        },
    },

    created() {
        Nova.$on(`ckeditor:hint:${this.fieldKey}:preview`, this.show)
        Nova.$on(`ckeditor:hint:${this.fieldKey}:preview:hide`, this.hide)
    },

    beforeUnmount() {
        Nova.$off(`ckeditor:hint:${this.fieldKey}:preview`, this.show)
        Nova.$off(`ckeditor:hint:${this.fieldKey}:preview:hide`, this.hide)
        this._removeDocListener()
    },

    methods: {
        show({ rect, hintHtml, displayText } = {}) {
            this.rect = rect
            this.hintHtml = hintHtml || ''
            this.displayText = displayText || ''
            this.isVisible = true
            this._addDocListener()
        },

        hide() {
            this.isVisible = false
            document.querySelectorAll('.ck-hint-highlight--active').forEach((el) => {
                el.classList.remove('ck-hint-highlight--active')
            })
            this._removeDocListener()
        },

        edit() {
            this.hide()
            Nova.$emit(`ckeditor:hint:${this.fieldKey}:trigger-open`)
        },

        _addDocListener() {
            this._removeDocListener()
            this._docHandler = (e) => {
                if (!e.target.closest('.ck-hint-tooltip')) {
                    this.hide()
                }
            }
            document.addEventListener('mousedown', this._docHandler)
        },

        _removeDocListener() {
            if (this._docHandler) {
                document.removeEventListener('mousedown', this._docHandler)
                this._docHandler = null
            }
        },
    },
}
</script>

<style>
.ck-hint-tooltip {
    position: fixed;
    transform: translateX(-50%) translateY(-100%);
    background: #fff;
    border: 3px solid #000;
    border-radius: 12px;
    padding: 0;
    min-width: 200px;
    max-width: 22rem;
    max-height: 360px;
    display: flex;
    flex-direction: column;
    z-index: 99990;
    pointer-events: all;
    font-size: 1rem;
    font-weight: 200;
    line-height: 1.5rem;
    color: #333;
    -webkit-font-smoothing: antialiased;
}

.ck-hint-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 7px solid transparent;
    border-top-color: #000;
}
.ck-hint-tooltip::before {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #fff;
    z-index: 1;
    margin-top: -2px;
}

.ck-hint-tooltip__name {
    font-size: 1rem;
    font-weight: 300;
    color: #fff;
    background: #000;
    padding: 0.5rem 0.75rem;
}

.ck-hint-tooltip__desc {
    font-size: 0.9rem;
    font-weight: 200;
    color: #333;
    line-height: 1.4rem;
    padding: 0.75rem 0.75rem 0.6rem;
    overflow-y: auto;
    flex: 1;
}

.ck-hint-tooltip__desc p {
    margin-bottom: 0.5rem;
}

.ck-hint-tooltip__desc p:last-child {
    margin-bottom: 0;
}

.ck-hint-tooltip__desc a {
    color: #000;
}

.ck-hint-tooltip__desc a:hover {
    text-decoration: underline;
}

.ck-hint-tooltip__edit {
    width: 100%;
    padding: 0.45rem 0.75rem;
    background: #f5f5f5;
    border: none;
    border-top: 1px solid #e0e0e0;
    border-radius: 0 0 9px 9px;
    color: #666;
    font-size: 0.8rem;
    font-weight: 200;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    cursor: pointer;
    text-align: center;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}
.ck-hint-tooltip__edit:hover {
    background: #ebebeb;
    color: #333;
}
</style>
