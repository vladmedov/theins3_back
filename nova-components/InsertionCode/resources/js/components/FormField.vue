<template>
  <div class="ic">
    <div class="ic__head">
      <div class="ic__head-main">
        <label class="ic__switch" :class="{ 'ic__switch--on': enabled }">
          <input type="checkbox" v-model="enabled" />
          <span class="ic__switch-track">
            <span class="ic__switch-thumb" />
          </span>
          <span class="ic__switch-label">{{ enabled ? 'Вкл' : 'Выкл' }}</span>
        </label>
        <span class="ic__label">{{ field.name }}</span>
      </div>
    </div>

    <div class="ic__body">
      <transition name="ic-slide" mode="out-in">
        <div v-if="enabled && insertionCode" key="code" class="ic__code-wrap">
          <div class="ic__code">
            <input
              type="text"
              :value="insertionCode"
              readonly
              ref="codeInput"
              class="ic__code-input"
              @click="selectAll"
            />
            <button
              type="button"
              class="ic__copy"
              :class="{ 'ic__copy--done': copied }"
              @click="copyCode"
              :title="copied ? (field.copiedTitle || 'Скопировано') : (field.copyTitle || 'Копировать')"
            >
              <template v-if="!copied">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                <span class="ic__copy-text">Копировать</span>
              </template>
              <template v-else>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span class="ic__copy-text">Скопировано</span>
              </template>
            </button>
          </div>
        </div>
        <div v-else key="hint" class="ic__hint">
          <svg class="ic__hint-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
          </svg>
          <span>{{ field.hintOff || 'Блок будет показан в своём обычном месте' }}</span>
        </div>
      </transition>
    </div>
  </div>
</template>

<script>
import { FormField, HandlesValidationErrors } from "laravel-nova";

export default {
  mixins: [FormField, HandlesValidationErrors],
  props: ["resourceName", "resourceId", "field"],

  data() {
    return {
      enabled: true,
      copied: false,
    };
  },

  computed: {
    insertionCode() {
      if (this.field.insertionCode) return this.field.insertionCode;
      if (!this.field.layoutType) return '';
      const key = this.findFlexibleGroupKey();
      if (!key) return '';
      return '{{ ' + this.field.layoutType + '_id' + key + ' }}';
    },
  },

  mounted() {
    this.setInitialValue();
  },

  watch: {
    field: {
      immediate: true,
      deep: true,
      handler() {
        this.setInitialValue();
      },
    },
  },

  methods: {
    setInitialValue() {
      const val = this.field.value;
      const defaultEnabled = this.field.defaultEnabled !== false;

      if (val === undefined || val === null || val === '') {
        this.enabled = defaultEnabled;
        return;
      }
      const explicitlyOff = val === false || val === '0' || val === 0;
      this.enabled = !explicitlyOff;
    },

    selectAll() {
      this.$refs.codeInput.select();
    },

    async copyCode() {
      try {
        await navigator.clipboard.writeText(this.insertionCode);
      } catch {
        this.$refs.codeInput.select();
        document.execCommand('copy');
      }
      this.copied = true;
      setTimeout(() => { this.copied = false; }, 1500);
    },

    fill(formData) {
      formData.append(this.field.attribute, this.enabled ? '1' : '0');
    },

    findFlexibleGroupKey() {
      let vm = this.$parent;
      for (let i = 0; i < 20 && vm; i++) {
        if (vm.group && vm.group.key) return vm.group.key;
        if (vm.layout && vm.layout.key) return vm.layout.key;
        vm = vm.$parent;
      }
      return null;
    },
  },
};
</script>

<style scoped>
.ic {
  --ic-accent: #e54839;
  --ic-bg: rgb(243, 242, 240);
  --ic-panel-border: #d6c396;
  --ic-border: #d8c49a;
  --ic-heading: #6b5530;
  --ic-text: #334155;
  --ic-muted: #64748b;
  --ic-code-bg: #fcfcfc;
}

.ic {
  padding: 1rem 1.25rem;
  background: var(--ic-bg);
  border-bottom: 1px solid var(--ic-panel-border);
}

.ic__head {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 1rem;
  margin-bottom: 0.75rem;
  color: var(--ic-heading);
}

.ic__head-main {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
}

.ic__label {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--ic-heading);
}

.ic__switch {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  user-select: none;
}

.ic__switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.ic__switch-track {
  width: 36px;
  height: 20px;
  background: var(--ic-border);
  border-radius: 10px;
  display: flex;
  align-items: center;
  padding: 0 2px;
  transition: background 0.2s ease, box-shadow 0.2s ease;
}

.ic__switch--on .ic__switch-track {
  background: var(--ic-accent);
  box-shadow: 0 0 0 1px var(--ic-accent);
}

.ic__switch-thumb {
  width: 16px;
  height: 16px;
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
  transition: transform 0.2s ease;
}

.ic__switch--on .ic__switch-thumb {
  transform: translateX(16px);
}

.ic__switch-label {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--ic-muted);
}

.ic__switch--on .ic__switch-label {
  color: var(--ic-text);
}

.ic__body {
  min-height: 2.5rem;
  display: flex;
  align-items: center;
}

/* Code block */
.ic__code-wrap {
  width: 100%;
}

.ic__code {
  display: flex;
  align-items: stretch;
  width: 100%;
  min-height: 40px;
  background: var(--ic-code-bg);
  border: 1px solid var(--ic-border);
  border-left: 3px solid var(--ic-accent);
  overflow: hidden;
}

.ic__code-input {
  flex: 1;
  border: none;
  background: transparent;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-family: 'SF Mono', 'Fira Code', 'Fira Mono', 'Consolas', monospace;
  color: var(--ic-text);
  letter-spacing: 0.02em;
  outline: none;
  min-width: 0;
  cursor: text;
}

.ic__code-input::placeholder {
  color: var(--ic-muted);
}

.ic__copy {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  padding: 0 0.875rem;
  border: none;
  border-left: 1px solid var(--ic-border);
  background: #000;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  flex-shrink: 0;
  transition: color 0.15s ease, background 0.15s ease;
}

.ic__copy:hover {
  background: #333;
  color: #fff;
}

.ic__copy--done {
  color: #059669;
}

.ic__copy--done:hover {
  color: #047857;
}

.ic__copy-text {
  white-space: nowrap;
}

@media (max-width: 480px) {
  .ic__copy-text {
    display: none;
  }
}

/* Hint when off */
.ic__hint {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #fff;
  border: 1px dashed var(--ic-border);
  font-size: 0.8125rem;
  color: var(--ic-muted);
}

.ic__hint-icon {
  flex-shrink: 0;
  opacity: 0.8;
}

/* Transitions */
.ic-slide-enter-active,
.ic-slide-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.ic-slide-enter-from,
.ic-slide-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
