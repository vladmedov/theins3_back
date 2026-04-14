<template>
    <teleport to="body">
        <transition name="ck-hint-fade">
            <div v-if="isOpen" class="ck-hint-overlay" @mousedown.self="close">
                <div class="ck-hint-modal">
                    <div class="ck-hint-modal__header">
                        <span class="ck-hint-modal__title">ПОДСКАЗКА</span>
                        <button type="button" class="ck-hint-modal__close" @click="close">✕</button>
                    </div>
                    <div class="ck-hint-modal__body">
                        <div class="ck-termin-field">
                            <label class="ck-termin-label">Описание</label>
                            <div class="ck-termin-rich-editor">
                                <textarea ref="hintDescEditor" class="hidden" rows="1" />
                            </div>
                        </div>
                    </div>
                    <div class="ck-termin-modal__footer">
                        <div class="ck-termin-footer-summary">
                            <span class="ck-termin-selected-label">
                                В тексте: <strong>{{ displayText || '—' }}</strong>
                            </span>
                        </div>
                        <div class="ck-termin-actions">
                            <button type="button" class="ck-termin-btn ck-termin-btn--cancel" @click="close">
                                Отмена
                            </button>
                            <button
                                type="button"
                                class="ck-termin-btn ck-termin-btn--confirm"
                                :disabled="!displayText.trim()"
                                @click="save"
                            >
                                Сохранить подсказку
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
import { buildTerminDescriptionCkConfig } from '../termin-picker/terminDescriptionEditorConfig'

export default {
    name: 'HintPickerModal',

    props: {
        fieldKey: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isOpen: false,
            displayText: '',
            description: '',
            hintDescEditorInstance: null,
        }
    },

    watch: {
        isOpen(val) {
            if (typeof document === 'undefined') {
                return
            }
            document.body.classList.toggle('ck-hint-modal-open', !!val)
            if (val) {
                this.$nextTick(() => this.mountHintDescriptionEditor())
            } else {
                this.destroyHintDescriptionEditor()
            }
        },
    },

    created() {
        Nova.$on(`ckeditor:hint:${this.fieldKey}:open`, this.open)
    },

    beforeUnmount() {
        if (typeof document !== 'undefined') {
            document.body.classList.remove('ck-hint-modal-open')
        }
        this.destroyHintDescriptionEditor()
        Nova.$off(`ckeditor:hint:${this.fieldKey}:open`, this.open)
    },

    methods: {
        open({ selectedText, hintHtml } = {}) {
            this.displayText = selectedText || ''
            this.description = hintHtml || ''
            this.isOpen = true
        },

        close() {
            this.isOpen = false
        },

        syncDescriptionFromEditor() {
            if (this.hintDescEditorInstance) {
                this.description = this.hintDescEditorInstance.getData()
            }
        },

        handleHintEditorKeydown(event, data) {
            if (['Tab', '/'].includes(data.key) || [191, 9].includes(data.keyCode)) {
                data.stopPropagation()
            }
        },

        hintEditorResizeFix(editor, writer) {
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

        mountHintDescriptionEditor() {
            this.destroyHintDescriptionEditor()

            this.$nextTick(() => {
                const el = this.$refs.hintDescEditor
                if (!el) {
                    return
                }

                let built
                try {
                    built = buildTerminDescriptionCkConfig(`${this.fieldKey}-hint`)
                } catch (e) {
                    console.error(e)
                    Nova.error(e.message)

                    return
                }

                const { config, toolbar } = built

                CkEditor.create(el, config)
                    .then((editor) => {
                        this.hintDescEditorInstance = markRaw(editor)
                        editor.setData(this.description || '')

                        editor.model.document.on(
                            'change',
                            debounce(() => {
                                this.description = editor.getData()
                            }, 100),
                            { priority: 'lowest' },
                        )

                        editor.editing.view.document.on('keydown', this.handleHintEditorKeydown, {
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

                            this.hintEditorResizeFix(editor, writer)
                        })
                    })
                    .catch((e) => {
                        console.error(e)
                        Nova.error(e.toString())
                    })
            })
        },

        destroyHintDescriptionEditor() {
            if (!this.hintDescEditorInstance) {
                return
            }

            const ed = this.hintDescEditorInstance
            this.hintDescEditorInstance = null

            ed.destroy().catch(() => {})
        },

        save() {
            if (!this.displayText.trim()) {
                return
            }
            this.syncDescriptionFromEditor()
            Nova.$emit(`ckeditor:hint:${this.fieldKey}:apply`, {
                text: this.displayText.trim(),
                descriptionHtml: this.description.trim(),
            })
            this.close()
        },
    },
}
</script>

<style>
.ck-hint-fade-enter-active,
.ck-hint-fade-leave-active {
    transition: opacity 0.15s ease;
}
.ck-hint-fade-enter-from,
.ck-hint-fade-leave-to {
    opacity: 0;
}
.ck-hint-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ck-hint-modal {
    background: #fff;
    border-radius: 12px;
    border: 3px solid #000;
    width: 520px;
    max-width: 95vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ck-hint-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e5e5;
}
.ck-hint-modal__title {
    font-weight: 600;
}
.ck-hint-modal__close {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
}
.ck-hint-modal__body {
    padding: 16px;
    flex: 1;
    overflow: auto;
}

/* Согласовано с футером модалки термина */
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
    white-space: normal;
    word-break: break-word;
}
.ck-termin-selected-label {
    font-size: 13px;
    color: #374151;
    min-width: 0;
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

.ck-termin-field {
    margin-bottom: 0;
}
.ck-termin-label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 500;
}
.ck-termin-rich-editor .ck.ck-reset.ck-editor {
    width: 100%;
}
.ck-termin-rich-editor .ck.ck-editor__editable_inline {
    min-height: 80px;
}
body.ck-hint-modal-open .ck.ck-balloon-panel,
body.ck-hint-modal-open .ck.ck-dropdown__panel {
    z-index: 100000 !important;
}
</style>
