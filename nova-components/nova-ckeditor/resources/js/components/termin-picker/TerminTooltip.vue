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
                top:  (this.rect.top - 10) + 'px',
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
            this._removeDocListener()
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
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    padding: 10px 14px;
    min-width: 200px;
    max-width: 280px;
    z-index: 99990;
    pointer-events: all;
}

/* Arrow pointing down */
.ck-termin-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #e5e7eb;
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
    margin-top: -1px;
}

.ck-termin-tooltip__state {
    font-size: 13px;
    color: #9ca3af;
    padding: 4px 0;
}

.ck-termin-tooltip__name {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 2px;
}

.ck-termin-tooltip__desc {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 8px;
    line-height: 1.4;
}

.ck-termin-tooltip__edit {
    width: 100%;
    padding: 5px 10px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    color: #1d4ed8;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
    transition: background 0.1s;
    margin-top: 4px;
}
.ck-termin-tooltip__edit:hover {
    background: #dbeafe;
}
</style>
