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
                        <!-- Display word -->
                        <div class="ck-termin-field">
                            <label class="ck-termin-label">
                                Слово в тексте
                                <span class="ck-termin-hint">(склонённая форма)</span>
                            </label>
                            <input
                                ref="displayInput"
                                v-model="displayText"
                                type="text"
                                class="ck-termin-input"
                                placeholder="например: реформе"
                            />
                        </div>

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
                                <span v-if="termin.description" class="ck-termin-item__desc">
                                    {{ stripMarkdown(termin.description) }}
                                </span>
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
                            <div class="ck-md-editor">
                                <div class="ck-md-toolbar">
                                    <button type="button" class="ck-md-btn" title="Жирный"   @mousedown.prevent @click="mdWrap('descTextarea', '**', '**')"><b>B</b></button>
                                    <button type="button" class="ck-md-btn" title="Курсив"   @mousedown.prevent @click="mdWrap('descTextarea', '_', '_')"><i>I</i></button>
                                    <button type="button" class="ck-md-btn" title="Ссылка"   @mousedown.prevent @click="mdWrap('descTextarea', '[', '](url)')">🔗</button>
                                    <button type="button" class="ck-md-btn" title="Картинка" @mousedown.prevent @click="mdWrap('descTextarea', '![', '](url)')">🖼</button>
                                </div>
                                <textarea
                                    ref="descTextarea"
                                    v-model="createDescription"
                                    class="ck-termin-input ck-termin-textarea"
                                    placeholder="Краткое определение..."
                                    rows="4"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Footer: search view -->
                    <div v-if="!creating" class="ck-termin-modal__footer">
                        <span v-if="selectedTermin" class="ck-termin-selected-label">
                            Термин: <strong>{{ selectedTermin.termin }}</strong>
                        </span>
                        <span v-else class="ck-termin-selected-label ck-termin-selected-label--empty">
                            Выберите термин из списка
                        </span>
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
export default {
    name: 'TerminPickerModal',

    props: {
        fieldKey: {
            type: String,
            required: true,
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
        }
    },

    created() {
        Nova.$on(`ckeditor:termin:${this.fieldKey}:open`, this.open)
    },

    beforeUnmount() {
        Nova.$off(`ckeditor:termin:${this.fieldKey}:open`, this.open)
        clearTimeout(this.debounceTimer)
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
                // Editing an existing termin: load it by ID and pre-select it
                await this.loadById(terminId)
                this.$nextTick(() => this.$refs.displayInput?.focus())
            } else {
                // New termin: use selected text as initial search query
                this.$nextTick(() => {
                    this.$refs.displayInput?.focus()
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
                    params: { id },
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
                    params: { q: this.searchQuery },
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
            if (!this.displayText.trim()) {
                this.displayText = termin.termin
            }
        },

        confirm() {
            if (!this.selectedTermin || !this.displayText.trim()) return

            Nova.$emit(`ckeditor:termin:${this.fieldKey}:insert`, {
                id: this.selectedTermin.id,
                text: this.displayText.trim(),
            })

            this.close()
        },

        // Insert Markdown syntax around the selected text in a textarea ref
        mdWrap(refName, before, after) {
            const el = this.$refs[refName]
            if (!el) return
            const start = el.selectionStart
            const end   = el.selectionEnd
            const value = this.createDescription
            const selected = value.slice(start, end) || 'текст'
            const inserted = before + selected + after
            this.createDescription = value.slice(0, start) + inserted + value.slice(end)
            this.$nextTick(() => {
                el.focus()
                // Place cursor after the inserted text (or select 'текст' placeholder)
                const selStart = start + before.length
                const selEnd   = selStart + (end > start ? end - start : 'текст'.length)
                el.setSelectionRange(selStart, selEnd)
            })
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
            if (!this.createName.trim() || this.creating === 'saving') return
            this.creating = 'saving'
            try {
                const { data } = await Nova.request().post(
                    '/nova-vendor/nova-ckeditor/termins',
                    {
                        termin:      this.createName.trim(),
                        description: this.createDescription.trim(),
                    }
                )
                this.termins        = [data]
                this.selectedTermin = data
                this.creating       = false
                if (!this.displayText.trim()) {
                    this.displayText = data.termin
                }
            } catch (e) {
                console.error('[TerminPicker] create error:', e)
                this.creating = 'form'
            }
        },

        stripMarkdown(text) {
            if (!text) return ''
            return text.replace(/[#*`_~[\]()>]/g, '').substring(0, 120)
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
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.ck-termin-modal__title {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.ck-termin-modal__close {
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    font-size: 18px;
    line-height: 1;
    padding: 4px;
    border-radius: 4px;
}
.ck-termin-modal__close:hover {
    color: #374151;
    background: #f3f4f6;
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
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
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
    background: #eff6ff;
}
.ck-termin-item--selected {
    background: #dbeafe;
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

.ck-termin-modal__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-top: 1px solid #e5e7eb;
    gap: 12px;
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
    gap: 8px;
    flex-shrink: 0;
}

.ck-termin-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: background 0.1s, opacity 0.1s;
}

.ck-termin-btn--cancel {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}
.ck-termin-btn--cancel:hover {
    background: #e5e7eb;
}

.ck-termin-btn--confirm {
    background: #3b82f6;
    color: #fff;
}
.ck-termin-btn--confirm:hover:not(:disabled) {
    background: #2563eb;
}
.ck-termin-btn--confirm:disabled {
    background: #93c5fd;
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
    color: #2563eb;
    font-size: 13px;
    cursor: pointer;
    transition: background 0.12s;
}
.ck-termin-create-btn:hover {
    background: #eff6ff;
}
.ck-termin-create-btn__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 1.5px solid #2563eb;
    font-size: 15px;
    line-height: 1;
    font-weight: 400;
    color: #2563eb;
}
.ck-termin-create-btn__text {
    font-weight: 500;
}
.ck-termin-create-btn__text em {
    font-style: normal;
    font-weight: 400;
}


.ck-md-toolbar {
    display: flex;
    gap: 2px;
    padding: 4px 6px;
    background: #f9fafb;
    border: 1px solid #d1d5db;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
}

.ck-md-btn {
    padding: 3px 8px;
    background: none;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    color: #374151;
    line-height: 1.4;
    transition: background 0.1s, border-color 0.1s;
}
.ck-md-btn:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
}

.ck-md-editor {
    display: flex;
    flex-direction: column;
}
.ck-md-editor .ck-termin-textarea {
    border-radius: 0 0 8px 8px;
    margin-top: -1px;
}

.ck-termin-textarea {
    resize: vertical;
    min-height: 68px;
    font-family: ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, monospace;
    font-size: 13px;
}


/* Visual highlight of termin spans inside the CKEditor editing area */
.ck-content .ck-termin-highlight {
    background: #dbeafe;
    border-bottom: 2px solid #3b82f6;
    border-radius: 2px;
    padding: 0 1px;
    cursor: default;
}
</style>
