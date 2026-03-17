<template>
  <PanelItem :field="field">
    <template #value>
      <div class="ic-detail">
        <template v-if="field.value && field.insertionCode">
          <div class="ic-detail__code">
            <code class="ic-detail__code-text">{{ field.insertionCode }}</code>
            <button
              type="button"
              class="ic-detail__copy"
              :class="{ 'ic-detail__copy--done': copied }"
              @click="copyCode"
              :title="copied ? (field.copiedTitle || 'Скопировано') : (field.copyTitle || 'Копировать')"
            >
              <svg v-if="!copied" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              <span>{{ copied ? (field.copiedTitle || 'Скопировано') : (field.copyTitle || 'Копировать') }}</span>
            </button>
          </div>
        </template>
        <span v-else class="ic-detail__off">Выкл</span>
      </div>
    </template>
  </PanelItem>
</template>

<script>
export default {
  props: ['resource', 'resourceName', 'resourceId', 'field'],

  data() {
    return { copied: false };
  },

  methods: {
    async copyCode() {
      if (!this.field.insertionCode) return;
      try {
        await navigator.clipboard.writeText(this.field.insertionCode);
      } catch {
        return;
      }
      this.copied = true;
      setTimeout(() => { this.copied = false; }, 1500);
    },
  },
};
</script>

<style scoped>
.ic-detail {
  --ic-accent: #e54839;
  --ic-border: #e2e8f0;
  --ic-text: #334155;
  --ic-muted: #64748b;
  --ic-code-bg: #f1f5f9;
}

.ic-detail__code {
  display: inline-flex;
  align-items: center;
  gap: 0;
  max-width: 100%;
  background: var(--ic-code-bg);
  border: 1px solid var(--ic-border);
  border-left: 3px solid var(--ic-accent);
  overflow: hidden;
}

.ic-detail__code-text {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
  font-family: 'SF Mono', 'Fira Code', 'Fira Mono', 'Consolas', monospace;
  color: var(--ic-text);
  letter-spacing: 0.02em;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 320px;
}

.ic-detail__copy {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  padding: 0.375rem 0.625rem;
  border: none;
  border-left: 1px solid var(--ic-border);
  background: transparent;
  color: var(--ic-muted);
  font-size: 0.75rem;
  cursor: pointer;
  transition: color 0.15s ease, background 0.15s ease;
}

.ic-detail__copy:hover {
  background: #e2e8f0;
  color: var(--ic-text);
}

.ic-detail__copy--done {
  color: #059669;
}

.ic-detail__off {
  font-size: 0.8125rem;
  color: var(--ic-muted);
}
</style>
