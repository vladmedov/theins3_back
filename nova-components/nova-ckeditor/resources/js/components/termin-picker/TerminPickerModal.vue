<template>
    <teleport to="body">
        <transition name="ck-termin-fade">
            <div v-if="isOpen" class="ck-termin-overlay" @mousedown.self="close">
                <div class="ck-termin-modal">
                    <!-- Header -->
                    <div class="ck-termin-modal__header">
                        <span class="ck-termin-modal__title">
                            {{ creating ? 'Создать термин' : (isEditing ? 'Изменить термин' : 'Вставить термин') }}
                        </span>
                        <button class="ck-termin-modal__close" @click="close" type="button">✕</button>
                    </div>

                    <!-- Body: search view -->
                    <div v-if="!creating" class="ck-termin-modal__body">
                        <!-- Term search -->
                        <div class="ck-termin-field">
                            <label class="ck-termin-label">
                                Термин
                                <span class="ck-termin-hint">(поиск)</span>
                            </label>
                            <input
                                ref="searchInput"
                                v-model="searchQuery"
                                @input="onSearchInput"
                                type="text"
                                class="ck-termin-input"
                                placeholder="Начните вводить..."
                            />
                        </div>

                        <!-- Results -->
                        <div class="ck-termin-results">
                            <!-- Create button: shown at the top when no exact match exists -->
                            <button
                                v-if="showCreateButton"
                                type="button"
                                class="ck-termin-create-btn"
                                @click="startCreate"
                            >
                                <span class="ck-termin-create-btn__icon">+</span>
                                <span class="ck-termin-create-btn__text">
                                    Создать <em>«{{ searchQuery }}»</em>
                                </span>
                            </button>
                            <div v-if="loading" class="ck-termin-state">Загрузка...</div>
                            <div v-else-if="!loading && termins.length === 0" class="ck-termin-state">
                                {{ searchQuery ? 'Ничего не найдено' : 'Начните вводить для поиска' }}
                            </div>
                            <button
                                v-for="termin in termins"
                                :key="termin.id"
                                type="button"
                                class="ck-termin-item"
                                :class="{ 'ck-termin-item--selected': selectedTermin && selectedTermin.id === termin.id }"
                                @click="selectTermin(termin)"
                            >
                                <span class="ck-termin-item__name">{{ termin.termin }}</span>
                                <span v-if="termin.description" class="ck-termin-item__desc" v-html="htmlExcerpt(termin.description, 120)"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Body: create view -->
                    <div v-if="creating" class="ck-termin-modal__body">
                        <div class="ck-termin-field">
                            <label class="ck-termin-label">Название</label>
                            <input
                                ref="createNameInput"
                                v-model="createName"
                                type="text"
                                class="ck-termin-input"
                                placeholder="Название термина"
                            />
                        </div>
                        <div class="ck-termin-field">
                            <label class="ck-termin-label">
                                Описание
                                <span class="ck-termin-hint">(необязательно)</span>
                            </label>
                            <div class="ck-termin-rich-editor">
                                <textarea
                                    ref="terminDescEditor"
                                    class="hidden"
                                    rows="1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Selected term description preview -->
                    <div v-if="!creating && selectedTermin && selectedTermin.description" class="ck-termin-preview">
                        <div class="ck-termin-preview__label">Описание термина</div>
                        <div class="ck-termin-preview__body" v-html="selectedTermin.description"></div>
                    </div>

                    <!-- Footer: search view -->
                    <div v-if="!creating" class="ck-termin-modal__footer">
                        <div class="ck-termin-footer-summary">
                            <span class="ck-termin-selected-label">
                                В тексте: <strong>{{ displayText || '—' }}</strong>
                            </span>
                            <span v-if="selectedTermin" class="ck-termin-selected-label">
                                Термин: <strong>{{ selectedTermin.termin }}</strong>
                            </span>
                            <span v-else class="ck-termin-selected-label ck-termin-selected-label--empty">
                                Выберите термин из списка
                            </span>
                        </div>
                        <div class="ck-termin-actions">
                            <button type="button" class="ck-termin-btn ck-termin-btn--cancel" @click="close">
                                Отмена
                            </button>
                            <button
                                type="button"
                                class="ck-termin-btn ck-termin-btn--confirm"
                                :disabled="!selectedTermin || !displayText.trim()"
                                @click="confirm"
                            >
                                Вставить
                            </button>
                        </div>
                    </div>

                    <!-- Footer: create view -->
                    <div v-if="creating" class="ck-termin-modal__footer">
                        <div class="ck-termin-actions" style="margin-left: auto">
                            <button type="button" class="ck-termin-btn ck-termin-btn--cancel" @click="cancelCreate">
                                ← Назад
                            </button>
                            <button
                                type="button"
                                class="ck-termin-btn ck-termin-btn--confirm"
                                :disabled="!createName.trim() || creating === 'saving'"
                                @click="confirmCreate"
                            >
                                {{ creating === 'saving' ? 'Сохранение...' : 'Создать' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script>
import { markRaw } from 'vue'
import CkEditor from '../../ckeditor/ckeditor'
import debounce from 'lodash/debounce'
import { buildTerminDescriptionCkConfig } from './terminDescriptionEditorConfig'

export default {
    name: 'TerminPickerModal',

    props: {
        fieldKey: {
            type: String,
            required: true,
        },
        resourceName: {
            type: String,
            default: null,
        },
        resourceId: {
            type: [String, Number],
            default: null,
        },
    },

    data() {
        return {
            isOpen: false,
            isEditing: false,
            searchQuery: '',
            displayText: '',
            termins: [],
            selectedTermin: null,
            loading: false,
            debounceTimer: null,
            // create flow: false | 'form' | 'saving'
            creating: false,
            createName: '',
            createDescription: '',
            terminDescEditorInstance: null,
        }
    },

    watch: {
        creating(val) {
            if (val === 'form') {
                this.$nextTick(() => this.mountTerminDescriptionEditor())
            } else if (!val) {
                this.destroyTerminDescriptionEditor()
            }
        },

        isOpen(val) {
            if (typeof document === 'undefined') {
                return
            }
            document.body.classList.toggle('ck-termin-modal-open', !!val)
        },
    },

    created() {
        Nova.$on(`ckeditor:termin:${this.fieldKey}:open`, this.open)
    },

    beforeUnmount() {
        if (typeof document !== 'undefined') {
            document.body.classList.remove('ck-termin-modal-open')
        }
        Nova.$off(`ckeditor:termin:${this.fieldKey}:open`, this.open)
        clearTimeout(this.debounceTimer)
        this.destroyTerminDescriptionEditor()
    },

    computed: {
        hasExactMatch() {
            if (!this.searchQuery.trim()) return false
            const q = this.searchQuery.trim().toLowerCase()
            return this.termins.some(t => t.termin.toLowerCase() === q)
        },

        showCreateButton() {
            return this.searchQuery.trim() && !this.creating && !this.loading && !this.hasExactMatch
        },
    },

    methods: {
        async open({ selectedText, terminId } = {}) {
            this.displayText    = selectedText || ''
            this.searchQuery    = ''
            this.selectedTermin = null
            this.termins        = []
            this.isEditing      = !!terminId
            this.isOpen         = true

            if (terminId) {
                // Editing an existing termin: load it by ID, pre-select it,
                // then immediately run a search by its name to show neighbours
                await this.loadById(terminId)
                if (this.selectedTermin) {
                    this.searchQuery = this.selectedTermin.termin.replace(/\s*\(\d+\)\s*$/, '').trim()
                    await this.search()
                    // Ensure the pre-selected item stays highlighted after search
                    const found = this.termins.find(t => t.id === this.selectedTermin.id)
                    if (found) this.selectedTermin = found
                }
                this.$nextTick(() => this.$refs.searchInput?.focus())
            } else {
                // New termin: use selected text as initial search query
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus()
                    if (this.displayText) {
                        this.searchQuery = this.displayText
                        this.search()
                    }
                })
            }
        },

        async loadById(id) {
            this.loading = true
            try {
                const { data } = await Nova.request().get('/nova-vendor/nova-ckeditor/termins', {
                    params: {
                        id,
                        resourceName: this.resourceName,
                        resourceId: this.resourceId,
                    },
                })
                if (data.length) {
                    const termin = data[0]
                    this.termins        = data
                    this.selectedTermin = termin
                    this.searchQuery    = termin.termin
                }
            } catch (e) {
                console.error('[TerminPicker] loadById error:', e)
            } finally {
                this.loading = false
            }
        },

        close() {
            this.isOpen = false
            this.creating = false
        },

        onSearchInput() {
            this.selectedTermin = null
            this.creating = false
            clearTimeout(this.debounceTimer)
            this.debounceTimer = setTimeout(() => this.search(), 300)
        },

        async search() {
            if (!this.searchQuery.trim()) {
                this.termins = []
                return
            }

            this.loading = true
            try {
                const { data } = await Nova.request().get('/nova-vendor/nova-ckeditor/termins', {
                    params: {
                        q: this.searchQuery,
                        resourceName: this.resourceName,
                        resourceId: this.resourceId,
                    },
                })
                this.termins = data
            } catch (e) {
                console.error('[TerminPicker] search error:', e)
                this.termins = []
            } finally {
                this.loading = false
            }
        },

        selectTermin(termin) {
            this.selectedTermin = termin
        },

        confirm() {
            if (!this.selectedTermin || !this.displayText.trim()) return

            Nova.$emit(`ckeditor:termin:${this.fieldKey}:insert`, {
                id: this.selectedTermin.id,
                text: this.displayText.trim(),
            })

            this.close()
        },

        syncDescriptionFromEditor() {
            if (this.terminDescEditorInstance) {
                this.createDescription = this.terminDescEditorInstance.getData()
            }
        },

        handleTerminEditorKeydown(event, data) {
            if (['Tab', '/'].includes(data.key) || [191, 9].includes(data.keyCode)) {
                data.stopPropagation()
            }
        },

        terminEditorResizeFix(editor, writer) {
            const resizeObserver = new ResizeObserver(
                debounce((element) => {
                    const height = element[0].target.offsetHeight

                    if (height > 10) {
                        writer.setStyle('height', `${height}px`, editor.editing.view.document.getRoot())
                    }
                }, 100),
            )

            const innerEditor = editor.ui.view.element.getElementsByClassName('ck-editor__editable')

            if (innerEditor?.length) {
                resizeObserver.observe(innerEditor[0])
            }
        },

        mountTerminDescriptionEditor() {
            this.destroyTerminDescriptionEditor()

            this.$nextTick(() => {
                const el = this.$refs.terminDescEditor
                if (!el) {
                    return
                }

                let built
                try {
                    built = buildTerminDescriptionCkConfig(this.fieldKey)
                } catch (e) {
                    console.error(e)
                    Nova.error(e.message)

                    return
                }

                const { config, toolbar } = built

                CkEditor.create(el, config)
                    .then((editor) => {
                        // Must not store CKEditor in reactive state without markRaw — Vue's Proxy breaks the editor.
                        this.terminDescEditorInstance = markRaw(editor)
                        editor.setData(this.createDescription || '')

                        editor.model.document.on(
                            'change',
                            debounce(() => {
                                this.createDescription = editor.getData()
                            }, 100),
                            { priority: 'lowest' },
                        )

                        editor.editing.view.document.on('keydown', this.handleTerminEditorKeydown, {
                            priority: 'highest',
                        })

                        editor.editing.view.change((writer) => {
                            if (toolbar.height > 1) {
                                writer.setStyle(
                                    'height',
                                    `${toolbar.height}px`,
                                    editor.editing.view.document.getRoot(),
                                )
                            }

                            this.terminEditorResizeFix(editor, writer)
                        })
                    })
                    .catch((e) => {
                        console.error(e)
                        Nova.error(e.toString())
                    })
            })
        },

        destroyTerminDescriptionEditor() {
            if (!this.terminDescEditorInstance) {
                return
            }

            const ed = this.terminDescEditorInstance
            this.terminDescEditorInstance = null

            ed.destroy().catch(() => {})
        },

        startCreate() {
            this.createName        = this.searchQuery
            this.createDescription = ''
            this.creating          = 'form'
            this.$nextTick(() => this.$refs.createNameInput?.focus())
        },

        cancelCreate() {
            this.creating = false
        },

        async confirmCreate() {
            this.syncDescriptionFromEditor()
            if (!this.createName.trim() || this.creating === 'saving') return
            this.creating = 'saving'
            try {
                const { data } = await Nova.request().post(
                    '/nova-vendor/nova-ckeditor/termins',
                    {
                        termin:      this.createName.trim(),
                        description: this.createDescription.trim(),
                        resourceName: this.resourceName,
                        resourceId: this.resourceId,
                    }
                )
                this.termins        = [data]
                this.selectedTermin = data
                this.creating       = false
            } catch (e) {
                console.error('[TerminPicker] create error:', e)
                this.creating = 'form'
            }
        },

        htmlExcerpt(html, maxLen) {
            if (!html) return ''
            const div = document.createElement('div')
            div.innerHTML = html
            const text = (div.textContent || div.innerText || '').trim()
            if (text.length <= maxLen) return html
            return text.slice(0, maxLen).replace(/\s+\S*$/, '') + '…'
        },
    },
}
</script>

<style>
.ck-termin-fade-enter-active,
.ck-termin-fade-leave-active {
    transition: opacity 0.15s ease;
}
.ck-termin-fade-enter-from,
.ck-termin-fade-leave-to {
    opacity: 0;
}

.ck-termin-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ck-termin-modal {
    background: #fff;
    border-radius: 12px;
    border: 3px solid #000;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    width: 480px;
    max-width: 95vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ck-termin-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: #000;
}

.ck-termin-modal__title {
    font-size: 15px;
    font-weight: 300;
    color: #fff;
}

.ck-termin-modal__close {
    background: none;
    border: none;
    cursor: pointer;
    color: #999;
    font-size: 18px;
    line-height: 1;
    padding: 4px;
    border-radius: 4px;
}
.ck-termin-modal__close:hover {
    color: #fff;
    background: rgba(255,255,255,0.1);
}

.ck-termin-modal__body {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    overflow: hidden;
}

.ck-termin-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ck-termin-label {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
}

.ck-termin-hint {
    font-weight: 400;
    color: #9ca3af;
    font-size: 12px;
}

.ck-termin-input {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 14px;
    color: #111827;
    background: #fff;
    outline: none;
    box-sizing: border-box;
}
.ck-termin-input:focus {
    border-color: #000;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.08);
}

.ck-termin-results {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow-y: auto;
    max-height: 220px;
}

.ck-termin-state {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 72px;
    padding: 16px 24px;
    font-size: 13px;
    color: #9ca3af;
}

.ck-termin-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 10px 14px;
    border: none;
    border-bottom: 1px solid #f3f4f6;
    background: #fff;
    cursor: pointer;
    transition: background 0.1s;
}
.ck-termin-item:last-child {
    border-bottom: none;
}
.ck-termin-item:hover {
    background: #f5f5f5;
}
.ck-termin-item--selected {
    background: #ebebeb;
}

.ck-termin-item__name {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #111827;
}

.ck-termin-item__desc {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ck-termin-preview {
    margin: 0 20px 16px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    max-height: 160px;
    overflow-y: auto;
}

.ck-termin-preview__label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
    margin-bottom: 6px;
}

.ck-termin-preview__body {
    font-size: 13px;
    color: #374151;
    line-height: 1.5;
}

.ck-termin-preview__body p { margin: 0 0 6px; }
.ck-termin-preview__body p:last-child { margin-bottom: 0; }
.ck-termin-preview__body a { color: #E54839; }
.ck-termin-preview__body strong { font-weight: 600; }
.ck-termin-preview__body em { font-style: italic; }
.ck-termin-preview__body ul,
.ck-termin-preview__body ol { margin: 0 0 6px 18px; }

.ck-termin-modal__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-top: 1px solid #e0e0e0;
    background: #f5f5f5;
    gap: 12px;
}

.ck-termin-footer-summary {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.ck-termin-footer-summary .ck-termin-selected-label {
    flex: none;
}

.ck-termin-footer-summary .ck-termin-selected-label:first-child {
    white-space: normal;
    word-break: break-word;
}

.ck-termin-selected-label {
    font-size: 13px;
    color: #374151;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ck-termin-selected-label--empty {
    color: #9ca3af;
}

.ck-termin-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.ck-termin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    line-height: 1.25;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 200;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    cursor: pointer;
    border: none;
    transition: background 0.15s, color 0.15s;
}

.ck-termin-btn--cancel {
    background: #fff;
    color: #666;
    border: 1px solid #d0d0d0;
}
.ck-termin-btn--cancel:hover {
    background: #ebebeb;
    color: #333;
}

.ck-termin-btn--confirm {
    background: #000;
    color: #fff;
}
.ck-termin-btn--confirm:hover:not(:disabled) {
    background: #333;
}
.ck-termin-btn--confirm:disabled {
    background: #999;
    cursor: not-allowed;
    opacity: 0.7;
}

.ck-termin-create-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    text-align: left;
    padding: 9px 14px;
    background: none;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    color: #E54839;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.12s;
}
.ck-termin-create-btn:hover {
    background: #f5f5f5;
}
.ck-termin-create-btn__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid #E54839;
    font-size: 15px;
    line-height: 1;
    font-weight: 400;
    color: #E54839;
}
.ck-termin-create-btn__text {
    font-weight: 500;
}
.ck-termin-create-btn__text em {
    font-style: normal;
    font-weight: 400;
}


.ck-termin-rich-editor .ck.ck-reset.ck-editor {
    width: 100%;
}
.ck-termin-rich-editor .ck.ck-editor__editable_inline {
    min-height: 80px;
}

/* Visual highlight of termin spans inside the CKEditor editing area */
.ck-content .ck-termin-highlight {
    background: #dbeafe;
    border-bottom: 2px solid #3b82f6;
    border-radius: 2px;
    padding: 0 1px;
    cursor: default;
}

/*
 * Link / image UI opens in document.body with default z-index below our overlay (99999).
 * While the termin modal is open, lift CKEditor floating UI above the dimmer so clicks work.
 */
body.ck-termin-modal-open .ck.ck-balloon-panel,
body.ck-termin-modal-open .ck.ck-dropdown__panel {
    z-index: 100000 !important;
}
</style>
