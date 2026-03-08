<template>
    <teleport to="body">
        <div
            v-if="isVisible"
            class="ck-termin-tooltip"
            :style="style"
            @mousedown.stop
        >
            <div v-if="loading" class="ck-termin-tooltip__state">Загрузка...</div>
            <template v-else-if="termin">
                <div class="ck-termin-tooltip__name">{{ termin.termin }}</div>
                <div v-if="shortDesc" class="ck-termin-tooltip__desc" v-html="shortDesc"></div>
                <!-- .prevent keeps the editor focused so its selection is preserved -->
                <button
                    type="button"
                    class="ck-termin-tooltip__edit"
                    @mousedown.prevent
                    @click="edit"
                >
                    Редактировать
                </button>
            </template>
        </div>
    </teleport>
</template>

<script>
export default {
    name: 'TerminTooltip',

    props: {
        fieldKey: { type: String, required: true },
    },

    data() {
        return {
            isVisible: false,
            rect: null,
            termin: null,
            loading: false,
            currentTerminId: null,
            _docHandler: null,
        }
    },

    computed: {
        style() {
            if (!this.rect) return {}
            return {
                top:  (this.rect.top - 16) + 'px',
                left: (this.rect.left + this.rect.width / 2) + 'px',
            }
        },
        shortDesc() {
            const raw = this.termin?.description
            if (!raw) return ''

            // Extract plain text from HTML so we can safely measure length
            const div = document.createElement('div')
            div.innerHTML = raw
            const text = (div.textContent || div.innerText || '').trim()

            if (text.length <= 160) return raw   // short enough — render as-is

            // Truncate plain text at word boundary, then return as paragraph
            const truncated = text.slice(0, 160).replace(/\s+\S*$/, '') + '…'
            return `<p>${truncated}</p>`
        },
    },

    created() {
        Nova.$on(`ckeditor:termin:${this.fieldKey}:preview`,      this.show)
        Nova.$on(`ckeditor:termin:${this.fieldKey}:preview:hide`, this.hide)
    },

    beforeUnmount() {
        Nova.$off(`ckeditor:termin:${this.fieldKey}:preview`,      this.show)
        Nova.$off(`ckeditor:termin:${this.fieldKey}:preview:hide`, this.hide)
        this._removeDocListener()
    },

    methods: {
        async show({ terminId, rect }) {
            this.rect      = rect
            this.isVisible = true

            this._setActiveSpan(terminId)

            if (this.currentTerminId !== terminId) {
                this.currentTerminId = terminId
                this.termin  = null
                this.loading = true
                try {
                    const { data } = await Nova.request().get(
                        '/nova-vendor/nova-ckeditor/termins',
                        { params: { id: terminId } }
                    )
                    this.termin = data[0] || null
                } catch (e) {
                    console.error('[TerminTooltip]', e)
                } finally {
                    this.loading = false
                }
            }

            this._addDocListener()
        },

        hide() {
            this.isVisible = false
            this._clearActiveSpan()
            this._removeDocListener()
        },

        _setActiveSpan(terminId) {
            this._clearActiveSpan()
            document.querySelectorAll(`.ck-termin-highlight[data-id="${terminId}"]`).forEach(el => {
                el.classList.add('ck-termin-highlight--active')
            })
        },

        _clearActiveSpan() {
            document.querySelectorAll('.ck-termin-highlight--active').forEach(el => {
                el.classList.remove('ck-termin-highlight--active')
            })
        },

        edit() {
            this.hide()
            // Trigger openModal() in TerminPicker plugin.
            // @mousedown.prevent on this button keeps editor focused so the
            // selection (inside the termin span) is still valid when openModal runs.
            Nova.$emit(`ckeditor:termin:${this.fieldKey}:trigger-open`)
        },

        _addDocListener() {
            this._removeDocListener()
            this._docHandler = (e) => {
                if (!e.target.closest('.ck-termin-tooltip')) {
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
.ck-termin-tooltip {
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

/* Arrow pointing down */
.ck-termin-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 7px solid transparent;
    border-top-color: #000;
}
.ck-termin-tooltip::before {
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

.ck-termin-tooltip__state {
    font-size: 0.9rem;
    font-weight: 200;
    color: #9ca3af;
    padding: 0.6rem 0.75rem;
}

.ck-termin-tooltip__name {
    font-size: 1rem;
    font-weight: 300;
    color: #fff;
    background: #000;
    padding: 0.5rem 0.75rem;
}

.ck-termin-tooltip__desc {
    font-size: 0.9rem;
    font-weight: 200;
    color: #333;
    line-height: 1.4rem;
    padding: 0.75rem 0.75rem 0.6rem;
    overflow-y: auto;
    flex: 1;
}

.ck-termin-tooltip__desc p {
    margin-bottom: 0.5rem;
}

.ck-termin-tooltip__desc p:last-child {
    margin-bottom: 0;
}

.ck-termin-tooltip__desc a {
    color: #E54839;
}

.ck-termin-tooltip__desc a:hover {
    text-decoration: underline;
}

.ck-termin-tooltip__edit {
    width: 100%;
    padding: 0.4rem 0.75rem;
    background: #fff;
    border: none;
    border-top: 2px solid #000;
    color: #000;
    font-size: 0.9rem;
    font-weight: 200;
    cursor: pointer;
    text-align: center;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}
.ck-termin-tooltip__edit:hover {
    background: #000;
    color: #fff;
}
</style>
