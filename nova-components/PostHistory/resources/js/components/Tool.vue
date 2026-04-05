<template>
  <div class="history-viewer">
    <div class="history-top-bar" v-if="historyLoaded">
      <h1 class="history-post-title">
        <span class="nova-post-edit-page-title">
          <span class="nova-post-edit-page-title__type">{{ historyHeaderType }}</span>
          <span class="nova-post-edit-page-title__sep">: </span>
          <span class="nova-post-edit-page-title__headline">{{ historyHeaderHeadline }}</span>
        </span>
      </h1>
      <div class="history-top-actions">
        <a :href="getEditUrl()" class="history-edit-link">Редактировать пост</a>
      </div>
    </div>

    <p v-if="historyLoaded && !changes.length" class="history-empty-message">
      История изменений отсутствует.
    </p>

    <!-- Выбор изменения: сначала дата, потом время -->
    <div v-if="changes.length" class="history-picker">
      <div class="history-scroll-list history-date-list" role="tablist">
        <button
          v-for="dateKey in getUniqueDates()"
          :key="`date-${dateKey}`"
          type="button"
          :class="['history-item', { active: selectedDate === dateKey }]"
          :aria-selected="selectedDate === dateKey"
          role="tab"
          @click="selectDate(dateKey)"
        >
          {{ formatDateLabel(dateKey) }}
        </button>
      </div>
      <div
        v-if="selectedDate"
        class="history-scroll-list history-time-list"
        role="tablist"
      >
        <button
          v-for="item in getChangesForDate(selectedDate)"
          :key="item.change.id"
          type="button"
          :class="['history-item', { active: item.index === currentIndex }]"
          :aria-selected="item.index === currentIndex"
          role="tab"
          @click="selectChange(item.index)"
        >
          {{ formatTimeLabel(item.change.created_at) }}
        </button>
      </div>
    </div>

    <!-- Карточки изменений -->
    <div v-if="currentChange" class="change-details">
      <div class="history-meta-card">
        <div class="history-meta-top">
          <span :class="['status-chip', `status-chip--${currentChange.status}`]">
            {{ getStatusText(currentChange.status) }}
          </span>
        </div>
        <div class="history-meta-row">
          <span class="history-meta-label">Кем:</span>
          <span class="history-meta-value">{{ getCurrentChangeUserName() }}</span>
        </div>
        <div v-if="getCurrentChangeUserEmail()" class="history-meta-row">
          <span class="history-meta-label">Email:</span>
          <span class="history-meta-value">{{ getCurrentChangeUserEmail() }}</span>
        </div>
      </div>
      <div class="change-cards">
        <div 
          v-for="(change, field) in currentChange.changes" 
          :key="field"
          :class="field === 'content' ? 'content-field' : 'change-card'"
        >
          <h3 class="field-title">{{ field }}</h3>
          <div class="change-content" v-if="field === 'content'">
            <div 
              v-for="(block, blockId) in change.changes" 
              :key="blockId" 
              class="nested-card"
            >
              <strong>Блок ID: {{ blockId }} ({{ block.type }})</strong>
              <div class="block-separator"></div>
              <div v-if="isTextBlock(block)" class="text-unified-wrapper">
                <div class="view-mode-toggle">
                  <button
                    type="button"
                    class="view-mode-btn"
                    :class="{ active: isVisualMode(getBlockKey(blockId)) }"
                    @click="setBlockViewMode(getBlockKey(blockId), 'visual')"
                  >
                    Визуальный
                  </button>
                  <button
                    type="button"
                    class="view-mode-btn"
                    :class="{ active: !isVisualMode(getBlockKey(blockId)) }"
                    @click="setBlockViewMode(getBlockKey(blockId), 'html')"
                  >
                    HTML
                  </button>
                  <button
                    type="button"
                    class="view-mode-btn"
                    @click="copyMarkup(block, blockId)"
                  >
                    {{ isCopied(getBlockKey(blockId)) ? 'Скопировано' : 'Скопировать разметку' }}
                  </button>
                </div>

                <div
                  v-if="isVisualMode(getBlockKey(blockId))"
                  class="html-preview-render unified-frame"
                  v-html="buildVisualHtml(block)"
                ></div>
                <div v-else class="debug-html-block">
                  <pre class="debug-html-pre" v-html="buildRawHtmlDiff(block)"></pre>
                </div>

                <details class="post-history-debug">
                  <summary class="post-history-debug__summary">
                    Отладочный дамп (для копирования в чат)
                  </summary>
                  <textarea
                    class="post-history-debug__textarea"
                    readonly
                    rows="16"
                    :value="buildDebugDump(block, blockId)"
                    @focus="(e) => e.target.select()"
                  ></textarea>
                  <button
                    type="button"
                    class="post-history-debug__copy"
                    @click="copyPostHistoryDebug(block, blockId)"
                  >
                    {{ isDebugCopied(blockId) ? 'Скопировано' : 'Копировать дамп' }}
                  </button>
                </details>
              </div>
              <div v-else-if="isNonTextContentBlock(block)" class="plain-unified-wrapper">
                <div
                  class="plain-diff-value"
                  v-html="buildPlainWordDiffFromValues(extractPlainBlockValue(block.old), extractPlainBlockValue(block.new))"
                ></div>
              </div>
              <div v-else-if="block.old && !block.new" class="deleted-card">
                <div>{{ formatJson(block.old) }}</div>
              </div>
              <div v-else-if="block.new && !block.old" class="added-card">
                <div>{{ formatJson(block.new) }}</div>
              </div>
              <div v-else class="updated-card">
                <div class="flex-container">
                  <div class="old-value-block">
                    <span class="old-value-title">Было:</span>
                    <div class="deleted-value">{{ formatJson(block.old) }}</div>
                  </div>
                  <div class="new-value-block">
                    <span class="new-value-title">Стало:</span>
                    <div class="added-value">{{ formatJson(block.new) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="change-content" v-else-if="field === 'lead'">
            <div class="text-unified-wrapper">
              <div class="view-mode-toggle">
                <button
                  type="button"
                  class="view-mode-btn"
                  :class="{ active: isVisualMode(getBlockKey('__lead__')) }"
                  @click="setBlockViewMode(getBlockKey('__lead__'), 'visual')"
                >
                  Визуальный
                </button>
                <button
                  type="button"
                  class="view-mode-btn"
                  :class="{ active: !isVisualMode(getBlockKey('__lead__')) }"
                  @click="setBlockViewMode(getBlockKey('__lead__'), 'html')"
                >
                  HTML
                </button>
                <button
                  type="button"
                  class="view-mode-btn"
                  @click="copyMarkup(toTextLikeBlock(change), '__lead__')"
                >
                  {{ isCopied(getBlockKey('__lead__')) ? 'Скопировано' : 'Скопировать разметку' }}
                </button>
              </div>

              <div
                v-if="isVisualMode(getBlockKey('__lead__'))"
                class="html-preview-render unified-frame"
                v-html="buildVisualHtml(toTextLikeBlock(change))"
              ></div>
              <div v-else class="debug-html-block">
                <pre class="debug-html-pre" v-html="buildRawHtmlDiff(toTextLikeBlock(change))"></pre>
              </div>

              <details class="post-history-debug">
                <summary class="post-history-debug__summary">
                  Отладочный дамп (для копирования в чат)
                </summary>
                <textarea
                  class="post-history-debug__textarea"
                  readonly
                  rows="16"
                  :value="buildDebugDump(toTextLikeBlock(change), '__lead__')"
                  @focus="(e) => e.target.select()"
                ></textarea>
                <button
                  type="button"
                  class="post-history-debug__copy"
                  @click="copyPostHistoryDebug(toTextLikeBlock(change), '__lead__')"
                >
                  {{ isDebugCopied('__lead__') ? 'Скопировано' : 'Копировать дамп' }}
                </button>
              </details>
            </div>
          </div>
          <div class="change-content" v-else>
            <div class="plain-unified-wrapper">
              <div class="plain-diff-value" v-html="buildPlainWordDiff(change)"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { diffArrays, diffWordsWithSpace } from 'diff';

export default {
  props: ['resourceName', 'resourceId', 'panel'],

  data() {
    return {
      postTitle: '',
      postTypeLabel: '',
      changes: [],
      currentIndex: 0,
      currentChange: null,
      wordDiffOptions: null,
      blockViewModes: {},
      visualFrameHeights: {},
      copiedBlocks: {},
      copiedDebugBlocks: {},
      selectedDate: '',
      emptyPostTitleLabel: '—',
      historyLoaded: false,
    };
  },

  computed: {
    historyHeaderType() {
      const raw =
        String(this.postTypeLabel ?? '').trim() || String(this.resourceName ?? '').trim() || '-';
      return raw.toLocaleUpperCase('ru-RU');
    },
    historyHeaderHeadline() {
      const t = String(this.postTitle ?? '').trim();
      return t || this.emptyPostTitleLabel;
    },
  },

  methods: {
    getCurrentChangeUserName() {
      const user = this.currentChange?.user;
      if (!user) {
        return this.currentChange?.user_fullname || 'Неизвестный пользователь';
      }
      return user.name || user.fullname || this.currentChange?.user_fullname || 'Неизвестный пользователь';
    },

    getCurrentChangeUserEmail() {
      const user = this.currentChange?.user;
      return user?.email || '';
    },

    isTextBlock(block) {
      return block?.type === 'text';
    },

    isNonTextContentBlock(block) {
      return Boolean(block) && !this.isTextBlock(block);
    },

    getStatusText(status) {
      switch (status) {
        case 'created':
          return 'Публикация создана';
        case 'updated':
          return 'Публикация изменена';
        case 'deleted':
          return 'Публикация удалена';
        default:
          return 'Неизвестный статус';
      }
    },

    async loadChanges() {
      const { data } = await axios.get(`/nova-vendor/post-history/${this.resourceId}`);
      this.postTitle = data.post;
      this.postTypeLabel = data.type_label || '';
      if (data.empty_post_title_label) {
        this.emptyPostTitleLabel = data.empty_post_title_label;
      }
      this.changes = (data.changes || []).map((change) => {
        change.changes = this.parseChanges(change.changes);
        return change;
      });
      this.currentIndex = 0;
      this.selectedDate = this.changes.length ? this.getChangeDateKey(this.changes[0]) : '';
      if (!this.changes.length) {
        this.currentChange = null;
        this.historyLoaded = true;
        return;
      }
      try {
        await this.loadCurrentChange();
      } finally {
        this.historyLoaded = true;
      }
    },

    async loadCurrentChange() {
      const changeId = this.changes[this.currentIndex].id;
      const { data } = await axios.get(`/nova-vendor/post-history/${this.resourceId}/change/${changeId}`);
      this.currentChange = data;
      this.currentChange.changes = this.parseChanges(this.currentChange.changes);
    },

    selectChange(index) {
      this.currentIndex = index;
      this.selectedDate = this.changes[index] ? this.getChangeDateKey(this.changes[index]) : this.selectedDate;
      this.loadCurrentChange();
    },

    getChangeDateKey(change) {
      const date = new Date(change?.created_at);
      if (Number.isNaN(date.getTime())) {
        return '';
      }
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, '0');
      const d = String(date.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    },

    getUniqueDates() {
      const seen = new Set();
      const result = [];
      for (const change of this.changes) {
        const key = this.getChangeDateKey(change);
        if (!key || seen.has(key)) {
          continue;
        }
        seen.add(key);
        result.push(key);
      }
      return result;
    },

    getChangesForDate(dateKey) {
      return this.changes
        .map((change, index) => ({ change, index }))
        .filter((item) => this.getChangeDateKey(item.change) === dateKey);
    },

    selectDate(dateKey) {
      this.selectedDate = dateKey;
      const firstChangeForDate = this.getChangesForDate(dateKey)[0];
      if (firstChangeForDate) {
        this.selectChange(firstChangeForDate.index);
      }
    },

    formatDateLabel(dateKey) {
      if (!dateKey) {
        return '';
      }
      const [y, m, d] = dateKey.split('-');
      if (!y || !m || !d) {
        return dateKey;
      }
      return `${d}.${m}.${y}`;
    },

    formatTimeLabel(value) {
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return String(value ?? '');
      }
      return date.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      });
    },

    getEditUrl() {
      return `/admin/resources/${this.resourceName}/${this.resourceId}/edit`;
    },

    parseChanges(changes) {
      try {
        if (typeof changes === 'string') {
          changes = JSON.parse(changes);
        }
        return changes;
      } catch (e) {
        console.error('Ошибка парсинга JSON:', e);
        return changes;
      }
    },

    parseJsonString(value) {
      if (typeof value !== 'string') {
        return value;
      }

      const trimmed = value.trim();
      if (!trimmed || !['{', '[', '"'].includes(trimmed[0])) {
        return value;
      }

      try {
        return JSON.parse(trimmed);
      } catch (e) {
        return value;
      }
    },

    toTextLikeBlock(change) {
      return {
        type: 'text',
        old: { text: String(change?.old ?? '') },
        new: { text: String(change?.new ?? '') },
      };
    },

    extractTextHtml(value) {
      const normalized = this.normalizeForDisplay(value);
      if (normalized && typeof normalized === 'object' && typeof normalized.text === 'string') {
        return normalized.text;
      }
      return '';
    },

    isEmptyNewValue(value) {
      if (value === null || value === undefined) {
        return true;
      }
      if (typeof value === 'string') {
        return value.trim() === '' || value.trim() === '{}' || value.trim() === '[]';
      }
      if (Array.isArray(value)) {
        return value.length === 0;
      }
      if (typeof value === 'object') {
        if (Object.keys(value).length === 0) {
          return true;
        }
        if (typeof value.text === 'string' && value.text.trim() === '') {
          return true;
        }
      }

      return false;
    },

    isHtmlContentEmpty(html) {
      const source = String(html ?? '')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\u00a0/g, ' ')
        .replace(/\u200B/g, '')
        .trim();

      return source === '';
    },

    sanitizeHtmlForPreview(html) {
      if (!html) {
        return '';
      }

      let safe = String(html);
      safe = safe.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
      safe = safe.replace(/\son\w+=(["']).*?\1/gi, '');
      safe = safe.replace(/\shref=(["'])javascript:.*?\1/gi, ' href="#"');
      safe = safe.replace(/\ssrc=(["'])javascript:.*?\1/gi, '');
      return safe;
    },

    htmlToPlainText(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      return this.stripResidualHtmlTokens(doc.body?.textContent ?? '')
        .replace(/\s+/g, ' ')
        .trim();
    },

    htmlToDisplayText(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      return this.stripResidualHtmlTokens(doc.body?.textContent ?? '');
    },

    /**
     * Top-level content blocks in document order for visual diff.
     * Skips p/headings inside blockquote (blockquote carries full quote text once).
     * diffKey includes structural kind so plain h3 vs outline-heading h3 split as different blocks.
     */
    htmlToVisualBlocks(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      const body = doc.body;
      if (!body) {
        return [];
      }

      const blockSelector = 'p, h1, h2, h3, h4, h5, h6, blockquote, li';
      const blocks = Array.from(body.querySelectorAll(blockSelector)).filter((node) => {
        const t = node.tagName.toLowerCase();
        if (t === 'blockquote') {
          return true;
        }
        return !node.closest('blockquote');
      });

      const out = [];
      for (const node of blocks) {
        const withBreaks = (node.innerHTML || '').replace(/<br\s*\/?>/gi, '\n');
        const tmp = parser.parseFromString(`<div>${withBreaks}</div>`, 'text/html');
        const text = this.stripResidualHtmlTokens(tmp.body?.textContent ?? '').trim();
        if (!text) {
          continue;
        }
        const tag = node.tagName.toLowerCase();
        let kind = tag;
        if (tag === 'h3' && node.classList?.contains('outline-heading')) {
          kind = 'h3-outline';
        }
        const diffKey = `${kind}\u001f${text}`;
        out.push({ text, diffKey, kind });
      }
      return out;
    },

    htmlToVisualText(html) {
      const blocks = this.htmlToVisualBlocks(html);
      if (blocks.length > 0) {
        return blocks.map((b) => b.text).join('\n\n');
      }

      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      return this.stripResidualHtmlTokens(doc.body?.textContent ?? '');
    },

    escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    escapeHtmlAttribute(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    },

    decodeHtmlEntities(value) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = String(value ?? '');
      return textarea.value;
    },

    decodeHtmlEntitiesDeep(value) {
      let current = String(value ?? '');
      for (let i = 0; i < 3; i += 1) {
        const decoded = this.decodeHtmlEntities(current);
        if (decoded === current) {
          break;
        }
        current = decoded;
      }
      return current;
    },

    stripResidualHtmlTokens(text) {
      return String(text ?? '')
        // Normal tags/fragments like <span ...> or </a>
        .replace(/<[^>]*>?/g, ' ')
        // Broken leftovers after tokenized diff, e.g. "u>" / "span>" / "/span>"
        .replace(/\b\/?[a-zA-Z][\w:-]*>/g, ' ')
        // Rare escaped leftovers: "&lt;tag" / "tag&gt;"
        .replace(/&lt;\/?[a-zA-Z][\w:-]*/g, ' ')
        .replace(/\/?[a-zA-Z][\w:-]*&gt;/g, ' ')
        .replace(/\s{2,}/g, ' ');
    },

    extractAnchorMeta(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      const a = doc.body?.querySelector?.('a[href]');
      if (!a) {
        return null;
      }

      const href = a.getAttribute('href') || '';
      const text = (a.textContent || '').trim();
      if (!href || !text) {
        return null;
      }

      return /^\s*javascript:/i.test(href) ? { href: '#', text } : { href, text };
    },

    renderFormatLink(meta) {
      return `<a href="${this.escapeHtmlAttribute(meta.href)}" target="_blank" rel="noopener noreferrer">${this.escapeHtml(meta.text)}</a>`;
    },

    tokenizeWithSpaces(text) {
      return String(text ?? '').match(/\s+|[^\s]+/g) || [];
    },

    tokenizeHtmlForDebugDiff(html) {
      const source = String(html ?? '');
      // Keep whole tags as single tokens so character-level diff cannot glue
      // e.g. "<h3 " + "data-id=..." into invalid "<h3data-id=...".
      const tokens = [];
      const parts = source.split(/(<[^>]*>)/g);
      for (const part of parts) {
        if (part === '') {
          continue;
        }
        if (part.startsWith('<') && part.endsWith('>')) {
          tokens.push(part);
          continue;
        }
        const urlRegex = /(https?:\/\/[^\s"'<>]+)|(\s+)|([^\s]+)/g;
        let m;
        while ((m = urlRegex.exec(part)) !== null) {
          tokens.push(m[0]);
        }
      }
      return tokens;
    },

    getFormattingSignature(textNode) {
      const marks = [];
      let node = textNode.parentElement;

      while (node) {
        const tag = node.tagName?.toLowerCase();
        if (tag === 'strong' || tag === 'b' || tag === 'em' || tag === 'i' || tag === 'u') {
          marks.push(tag);
        } else if (/^h[1-6]$/.test(tag)) {
          marks.push(tag);
        } else if (tag === 'blockquote') {
          marks.push('blockquote');
        } else if (tag === 'a') {
          const href = node.getAttribute('href') || '';
          marks.push(`a:${href}`);
        }
        node = node.parentElement;
      }

      return marks.sort().join('|');
    },

    extractFormatTokenSignatures(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      const walker = document.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT, null);
      const tokens = [];

      let currentNode = walker.nextNode();
      while (currentNode) {
        const signature = this.getFormattingSignature(currentNode);
        const nodeTokens = this.tokenizeWithSpaces(currentNode.nodeValue || '');
        for (const token of nodeTokens) {
          tokens.push({ token, signature: token.trim() ? signature : '' });
        }
        currentNode = walker.nextNode();
      }

      return tokens;
    },

    buildFormatTokenSets(oldHtml, newHtml) {
      const oldTokens = this.extractFormatTokenSignatures(oldHtml);
      const newTokens = this.extractFormatTokenSignatures(newHtml);
      const changed = new Set();

      const length = Math.min(oldTokens.length, newTokens.length);
      for (let i = 0; i < length; i += 1) {
        const oldToken = oldTokens[i];
        const newToken = newTokens[i];

        // Keep it strict: only mark when visible token text is same.
        if (oldToken.token !== newToken.token) {
          continue;
        }

        if (!newToken.token.trim()) {
          continue;
        }

        if (oldToken.signature !== newToken.signature) {
          changed.add(i);
        }
      }

      return { changed };
    },

    renderTokenWithStyles(
      token,
      globalIndex,
      changedTokenIndices,
      strongTokenIndices,
      headingTokenIndices,
      quoteTokenIndices,
      baseClass = ''
    ) {
      if (!token.trim()) {
        return this.escapeHtml(token);
      }

      const classes = [];
      if (baseClass) {
        classes.push(baseClass);
      }
      if (changedTokenIndices.has(globalIndex)) {
        classes.push('diff-format-change');
      }
      if (strongTokenIndices.has(globalIndex)) {
        classes.push('is-strong');
      }
      if (headingTokenIndices.has(globalIndex)) {
        classes.push('is-heading');
      }
      if (quoteTokenIndices.has(globalIndex)) {
        classes.push('is-quote');
      }

      const escaped = this.escapeHtml(token);
      if (classes.length === 0) {
        return escaped;
      }

      return `<span class="${classes.join(' ')}">${escaped}</span>`;
    },

    renderEqualChunkWithMarkupDiff(
      text,
      changedTokenIndices,
      strongTokenIndices,
      headingTokenIndices,
      quoteTokenIndices,
      startIndex
    ) {
      const tokens = this.tokenizeWithSpaces(text);
      let rendered = '';

      for (let i = 0; i < tokens.length; i += 1) {
        const token = tokens[i];
        const globalIndex = startIndex + i;
        rendered += this.renderTokenWithStyles(
          token,
          globalIndex,
          changedTokenIndices,
          strongTokenIndices,
          headingTokenIndices,
          quoteTokenIndices
        );
      }

      return {
        html: rendered,
        nextIndex: startIndex + tokens.length,
      };
    },

    renderChangedParagraphDiff(
      oldParagraph,
      newParagraph,
      changedTokenIndices,
      strongTokenIndices,
      headingTokenIndices,
      quoteTokenIndices,
      startIndex
    ) {
      const oldTokens = this.tokenizeWithSpaces(String(oldParagraph ?? ''));
      const newTokens = this.tokenizeWithSpaces(String(newParagraph ?? ''));
      const parts = diffArrays(oldTokens, newTokens);
      let html = '';
      let nextIndex = startIndex;

      for (const part of parts) {
        const values = Array.isArray(part?.value) ? part.value : [];
        if (values.length === 0) {
          continue;
        }

        if (!part.added && !part.removed) {
          const value = values.join('');
          const rendered = this.renderEqualChunkWithMarkupDiff(
            value,
            changedTokenIndices,
            strongTokenIndices,
            headingTokenIndices,
            quoteTokenIndices,
            nextIndex
          );
          html += rendered.html;
          nextIndex = rendered.nextIndex;
        } else if (part.removed) {
          const value = values.join('');
          html += `<span class="diff-removed">${this.escapeHtml(value)}</span>`;
        } else if (part.added) {
          for (let ti = 0; ti < values.length; ti += 1) {
            html += this.renderTokenWithStyles(
              values[ti],
              nextIndex + ti,
              changedTokenIndices,
              strongTokenIndices,
              headingTokenIndices,
              quoteTokenIndices,
              'diff-added'
            );
          }
          nextIndex += values.length;
        }
      }

      return {
        html,
        nextIndex,
      };
    },

    extractAnchors(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      const anchors = Array.from(doc.body?.querySelectorAll?.('a[href]') || []);

      return anchors
        .map((a) => {
          const href = a.getAttribute('href') || '';
          const text = (a.textContent || '').replace(/\s+/g, ' ').trim();
          if (!href || !text) {
            return null;
          }
          return {
            href: /^\s*javascript:/i.test(href) ? '#' : href,
            text,
          };
        })
        .filter(Boolean);
    },

    replaceFirstLiteral(text, search, replaceWith) {
      if (!search) {
        return text;
      }
      const idx = text.indexOf(search);
      if (idx === -1) {
        return text;
      }
      return text.slice(0, idx) + replaceWith + text.slice(idx + search.length);
    },

    applyAnchorMarkupDiffs(diffHtml, oldHtml, newHtml) {
      const oldAnchors = this.extractAnchors(oldHtml);
      const newAnchors = this.extractAnchors(newHtml);
      let result = diffHtml;

      const texts = new Set([
        ...oldAnchors.map((item) => item.text),
        ...newAnchors.map((item) => item.text),
      ]);

      for (const text of texts) {
        const oldByText = oldAnchors.filter((item) => item.text === text);
        const newByText = newAnchors.filter((item) => item.text === text);

        // Markup diff for links applies only when the text existed before.
        if (oldByText.length === 0 || newByText.length === 0) {
          continue;
        }

        const oldSignature = oldByText.map((item) => item.href).sort().join('|');
        const newSignature = newByText.map((item) => item.href).sort().join('|');
        if (oldSignature === newSignature) {
          continue;
        }

        const escapedText = this.escapeHtml(text);
        if (newByText.length > 0) {
          const replacement = `<span class="diff-format-change">${this.renderFormatLink(newByText[0])}</span>`;
          result = this.replaceFirstLiteral(result, escapedText, replacement);
        } else {
          result = this.replaceFirstLiteral(
            result,
            escapedText,
            `<span class="diff-format-change">${escapedText}</span>`
          );
        }
      }

      // Keep links from "after" HTML clickable even when href was not changed.
      for (const anchor of newAnchors) {
        const escapedText = this.escapeHtml(anchor.text);
        const linkedText = this.renderFormatLink(anchor);

        // Skip if this text was already replaced as changed-markup link above.
        if (result.includes(`<span class="diff-format-change">${linkedText}</span>`)) {
          continue;
        }

        result = this.replaceFirstLiteral(result, escapedText, linkedText);
      }

      return result;
    },

    applyStructuralTagStyles(diffHtml, newHtml) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(newHtml);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      let result = diffHtml;

      // Same heading text can appear many times (e.g. several «Оглавление»). replaceFirstLiteral
      // always hits the first substring, so after the first wrap the next pass would match inside
      // the span and nest spans. Consume occurrences in document order via unique placeholders.
      const headingNodes = Array.from(doc.body?.querySelectorAll?.('h1, h2, h3, h4, h5, h6') || []);
      const headingPh = [];
      for (let hi = 0; hi < headingNodes.length; hi += 1) {
        const node = headingNodes[hi];
        const text = this.stripResidualHtmlTokens((node.textContent || '').replace(/\s+/g, ' ').trim());
        if (!text) {
          continue;
        }
        const escapedText = this.escapeHtml(text);
        const isOutlineHeading = node.classList?.contains('outline-heading');
        const headingClass = isOutlineHeading ? 'is-heading is-outline-heading' : 'is-heading';
        const ph = `\u2060POST_HISTORY_HEAD_${hi}_\u2060`;
        const next = this.replaceFirstLiteral(result, escapedText, ph);
        if (next === result) {
          continue;
        }
        result = next;
        headingPh.push({
          ph,
          html: `<span class="${headingClass}">${escapedText}</span>`,
        });
      }
      for (const { ph, html } of headingPh) {
        result = result.split(ph).join(html);
      }

      const quoteNodes = Array.from(doc.body?.querySelectorAll?.('blockquote') || []);
      const quotePh = [];
      for (let qi = 0; qi < quoteNodes.length; qi += 1) {
        const node = quoteNodes[qi];
        const text = this.stripResidualHtmlTokens((node.textContent || '').replace(/\s+/g, ' ').trim());
        if (!text) {
          continue;
        }
        const escapedText = this.escapeHtml(text);
        const ph = `\u2060POST_HISTORY_QUOTE_${qi}_\u2060`;
        const next = this.replaceFirstLiteral(result, escapedText, ph);
        if (next === result) {
          continue;
        }
        result = next;
        quotePh.push({ ph, html: `<span class="is-quote">${escapedText}</span>` });
      }
      for (const { ph, html } of quotePh) {
        result = result.split(ph).join(html);
      }

      return result;
    },

    collectInlineTagTextCounts(html) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(html);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      const counts = {
        strong: new Map(),
        u: new Map(),
      };

      const nodes = Array.from(doc.body?.querySelectorAll?.('strong, b, u') || []);
      for (const node of nodes) {
        const tag = node.tagName.toLowerCase() === 'b' ? 'strong' : node.tagName.toLowerCase();
        if (!counts[tag]) {
          continue;
        }
        const text = this.stripResidualHtmlTokens((node.textContent || '').replace(/\s+/g, ' ').trim());
        if (!text) {
          continue;
        }
        counts[tag].set(text, (counts[tag].get(text) || 0) + 1);
      }

      return counts;
    },

    applyInlineTagMarkup(diffHtml, oldHtml, newHtml) {
      const decodedHtml = this.decodeHtmlEntitiesDeep(newHtml);
      const parser = new DOMParser();
      const doc = parser.parseFromString(String(decodedHtml ?? ''), 'text/html');
      const oldCounts = this.collectInlineTagTextCounts(oldHtml);
      let result = diffHtml;

      const inlineNodes = Array.from(doc.body?.querySelectorAll?.('strong, b, u') || []);
      for (const node of inlineNodes) {
        const tag = node.tagName.toLowerCase() === 'b' ? 'strong' : node.tagName.toLowerCase();
        const text = this.stripResidualHtmlTokens((node.textContent || '').replace(/\s+/g, ' ').trim());
        if (!text) {
          continue;
        }

        const currentCount = oldCounts[tag]?.get(text) || 0;
        const shouldMarkFormatDiff = currentCount === 0;
        if (currentCount > 0) {
          oldCounts[tag].set(text, currentCount - 1);
        }

        result = this.wrapFirstTextWithTag(result, text, tag, shouldMarkFormatDiff);
      }

      return result;
    },

    hasAncestor(node, predicate) {
      let current = node?.parentElement;
      while (current) {
        if (predicate(current)) {
          return true;
        }
        current = current.parentElement;
      }
      return false;
    },

    wrapFirstTextWithTag(html, text, tagName, markFormatDiff = false) {
      const parser = new DOMParser();
      const doc = parser.parseFromString(`<div id="__root">${html}</div>`, 'text/html');
      const root = doc.getElementById('__root');
      if (!root) {
        return html;
      }

      const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
      let node = walker.nextNode();

      while (node) {
        const value = node.nodeValue || '';
        const idx = value.indexOf(text);
        if (idx !== -1) {
          const inSameTag = this.hasAncestor(node, (el) => el.tagName?.toLowerCase() === tagName);
          const inRemovedDiffSpan = this.hasAncestor(node, (el) => el.classList?.contains('diff-removed'));
          const inAddedDiffSpan = this.hasAncestor(node, (el) => el.classList?.contains('diff-added'));

          // Allow inline markup restore in normal and added content,
          // but never inject it into removed chunks.
          if (!inSameTag && !inRemovedDiffSpan) {
            const before = value.slice(0, idx);
            const match = value.slice(idx, idx + text.length);
            const after = value.slice(idx + text.length);

            const fragment = doc.createDocumentFragment();
            if (before) {
              fragment.appendChild(doc.createTextNode(before));
            }
            const wrapper = doc.createElement(tagName);
            wrapper.textContent = match;
            if (markFormatDiff && !inAddedDiffSpan) {
              const diffWrapper = doc.createElement('span');
              diffWrapper.className = 'diff-format-change';
              diffWrapper.appendChild(wrapper);
              fragment.appendChild(diffWrapper);
            } else {
              fragment.appendChild(wrapper);
            }
            if (after) {
              fragment.appendChild(doc.createTextNode(after));
            }

            node.parentNode.replaceChild(fragment, node);
            return root.innerHTML;
          }
        }

        node = walker.nextNode();
      }

      return root.innerHTML;
    },

    isTagOnlyFragment(html) {
      const source = String(html ?? '');
      return this.htmlToPlainText(source) === '' && /<[^>]+>/.test(source);
    },

    isResidualTagToken(value) {
      const token = String(value ?? '').trim().toLowerCase();
      if (!token) {
        return false;
      }

      // Broken tag-like tokens that can appear after HTML diff tokenization.
      return /^(<\/?[a-z][\w:-]*[^>]*>|\/?[a-z][\w:-]*>|\/?[a-z][\w:-]*|href=|target=|rel=)/.test(token);
    },

    tagDeltaFromResidualToken(value) {
      const token = String(value ?? '').trim().toLowerCase();
      if (token.startsWith('</') || token.startsWith('/') || /^\/?[a-z][\w:-]*>$/.test(token)) {
        return -1;
      }
      return 1;
    },

    tagDelta(html) {
      const source = String(html ?? '');
      const tagRegex = /<\/?([a-zA-Z][\w:-]*)\b[^>]*>/g;
      let match;
      let delta = 0;

      while ((match = tagRegex.exec(source)) !== null) {
        const tag = match[0];
        const isClosing = /^<\//.test(tag);
        const isSelfClosing = /\/>$/.test(tag);
        if (isSelfClosing) {
          continue;
        }
        delta += isClosing ? -1 : 1;
      }

      return delta;
    },

    getWordDiffOptions() {
      if (this.wordDiffOptions !== null) {
        return this.wordDiffOptions;
      }

      if (typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
        this.wordDiffOptions = {
          intlSegmenter: new Intl.Segmenter('ru', { granularity: 'word' }),
        };
      } else {
        this.wordDiffOptions = {};
      }

      return this.wordDiffOptions;
    },

    buildRenderedDiffHtml(block) {
      const oldHtml = this.sanitizeHtmlForPreview(this.extractTextHtml(block.old));
      const newHtml = this.sanitizeHtmlForPreview(this.extractTextHtml(block.new));
      const oldBlocks = this.htmlToVisualBlocks(oldHtml);
      const newBlocks = this.htmlToVisualBlocks(newHtml);
      const oldText = oldBlocks.map((b) => b.text).join('\n\n');
      const newText = newBlocks.map((b) => b.text).join('\n\n');
      const rawNewHtml = this.debugHtml(block.new);

      // Entire text block was removed: render all content as removed.
      if (
        (this.isEmptyNewValue(block?.new) || this.isHtmlContentEmpty(rawNewHtml) || (newText || '').trim() === '') &&
        (oldText || '').trim() !== ''
      ) {
        return `<span class="diff-removed">${this.escapeHtml(oldText)}</span>`;
      }

      const tokenSets = this.buildFormatTokenSets(oldHtml, newHtml);
      const changedMarkupTokenIndices = tokenSets.changed;
      const strongTokenIndices = new Set();
      const headingTokenIndices = new Set();
      const quoteTokenIndices = new Set();

      const oldKeys = oldBlocks.map((b) => b.diffKey);
      const newKeys = newBlocks.map((b) => b.diffKey);
      const paragraphOps = diffArrays(oldKeys, newKeys);

      let html = '';
      let newTokenIndex = 0;
      let oi = 0;
      let ni = 0;
      const headingClassByKind = (kind) => {
        if (kind === 'h3-outline') {
          return 'is-heading is-outline-heading';
        }
        if (typeof kind === 'string' && /^h[1-6]$/.test(kind)) {
          return 'is-heading';
        }
        return '';
      };

      // Keep added highlight for all inserted blocks (including <p> «Текст»).
      // Headings get additional block-like styling via CSS rules for `.diff-added > .is-heading`.
      const appendAddedBlock = (block) => {
        const paragraph = block.text;
        const tokens = this.tokenizeWithSpaces(paragraph);
        for (let ti = 0; ti < tokens.length; ti += 1) {
          html += this.renderTokenWithStyles(
            tokens[ti],
            newTokenIndex + ti,
            changedMarkupTokenIndices,
            strongTokenIndices,
            headingTokenIndices,
            quoteTokenIndices,
            'diff-added'
          );
        }
        newTokenIndex += tokens.length;
        html += '\n\n';
      };

      for (let i = 0; i < paragraphOps.length; i += 1) {
        const op = paragraphOps[i];
        const values = Array.isArray(op?.value) ? op.value : [];
        const n = values.length;

        if (!op.added && !op.removed) {
          for (let j = 0; j < n; j += 1) {
            const paragraph = newBlocks[ni].text;
            const rendered = this.renderEqualChunkWithMarkupDiff(
              paragraph,
              changedMarkupTokenIndices,
              strongTokenIndices,
              headingTokenIndices,
              quoteTokenIndices,
              newTokenIndex
            );
            html += rendered.html + '\n\n';
            newTokenIndex = rendered.nextIndex;
            oi += 1;
            ni += 1;
          }
        } else if (op.removed) {
          const nextOp = paragraphOps[i + 1];
          if (nextOp?.added) {
            const newVals = Array.isArray(nextOp.value) ? nextOp.value : [];
            const oldChunkLen = n;
            const newChunkLen = newVals.length;
            const pairedCount = Math.min(oldChunkLen, newChunkLen);

            for (let pi = 0; pi < pairedCount; pi += 1) {
              const ob = oldBlocks[oi + pi];
              const nb = newBlocks[ni + pi];
              if (ob.diffKey === nb.diffKey) {
                const rendered = this.renderChangedParagraphDiff(
                  ob.text,
                  nb.text,
                  changedMarkupTokenIndices,
                  strongTokenIndices,
                  headingTokenIndices,
                  quoteTokenIndices,
                  newTokenIndex
                );
                html += rendered.html + '\n\n';
                newTokenIndex = rendered.nextIndex;
              } else if (ob.text === nb.text) {
                // Same visible text, but different structural kind (e.g. h3 -> outline-heading):
                // treat as formatting change, not remove/add.
                const rendered = this.renderEqualChunkWithMarkupDiff(
                  nb.text,
                  changedMarkupTokenIndices,
                  strongTokenIndices,
                  headingTokenIndices,
                  quoteTokenIndices,
                  newTokenIndex
                );
                html += `<span class="diff-format-change">${rendered.html}</span>\n\n`;
                newTokenIndex = rendered.nextIndex;
              } else {
                const oldHeadingClass = headingClassByKind(ob.kind);
                const newHeadingClass = headingClassByKind(nb.kind);
                if (oldHeadingClass && newHeadingClass) {
                  html += `<span class="diff-removed"><span class="${oldHeadingClass}">${this.escapeHtml(ob.text)}</span></span>`;
                  html += `<span class="diff-added"><span class="${newHeadingClass}">${this.escapeHtml(nb.text)}</span></span>\n\n`;
                  newTokenIndex += this.tokenizeWithSpaces(nb.text).length;
                  continue;
                }
                const rendered = this.renderChangedParagraphDiff(
                  ob.text,
                  nb.text,
                  changedMarkupTokenIndices,
                  strongTokenIndices,
                  headingTokenIndices,
                  quoteTokenIndices,
                  newTokenIndex
                );
                html += rendered.html + '\n\n';
                newTokenIndex = rendered.nextIndex;
              }
            }

            for (let pi = pairedCount; pi < oldChunkLen; pi += 1) {
              html += `<span class="diff-removed">${this.escapeHtml(oldBlocks[oi + pi].text)}</span>\n\n`;
            }
            for (let pi = pairedCount; pi < newChunkLen; pi += 1) {
              appendAddedBlock(newBlocks[ni + pi]);
            }

            oi += oldChunkLen;
            ni += newChunkLen;
            i += 1;
            continue;
          }

          for (let j = 0; j < n; j += 1) {
            html += `<span class="diff-removed">${this.escapeHtml(oldBlocks[oi].text)}</span>\n\n`;
            oi += 1;
          }
        } else if (op.added) {
          for (let j = 0; j < n; j += 1) {
            appendAddedBlock(newBlocks[ni]);
            ni += 1;
          }
        }
      }

      const withLinkDiff = this.applyAnchorMarkupDiffs(html.trim(), oldHtml, newHtml);
      const withStructuralStyles = this.applyStructuralTagStyles(withLinkDiff, newHtml);
      const withInlineMarkup = this.applyInlineTagMarkup(withStructuralStyles, oldHtml, newHtml);
      return withInlineMarkup || '<span class="text-muted">Без изменений</span>';
    },

    debugHtml(value) {
      return this.decodeHtmlEntitiesDeep(this.extractTextHtml(value));
    },

    getVisualFrameStyle(blockKey) {
      const measured = this.visualFrameHeights[blockKey];
      if (measured) {
        return { height: `${measured}px` };
      }
      return { height: '320px' };
    },

    onVisualFrameLoad(blockId, event) {
      const iframe = event?.target;
      if (!iframe?.contentWindow?.document?.body) {
        return;
      }

      const doc = iframe.contentWindow.document;
      const root = doc.getElementById('__diff-root');
      const body = doc.body;
      const measureAndStore = () => {
        const contentHeight = Math.max(
          root?.scrollHeight || 0,
          root?.offsetHeight || 0,
          root?.getBoundingClientRect?.().height || 0,
          body?.scrollHeight || 0,
          body?.offsetHeight || 0
        );

        const height = Math.max(Math.ceil(contentHeight + 2), 120);
        this.visualFrameHeights = {
          ...this.visualFrameHeights,
          [blockId]: height,
        };
      };

      // Wait one frame so margins/fonts are reflected in final layout.
      requestAnimationFrame(() => {
        requestAnimationFrame(measureAndStore);
      });
    },

    buildRawHtmlDiff(block) {
      const oldHtml = this.debugHtml(block.old);
      const newHtml = this.debugHtml(block.new);
      const oldTokens = this.tokenizeHtmlForDebugDiff(oldHtml);
      const newTokens = this.tokenizeHtmlForDebugDiff(newHtml);
      const ops = diffArrays(oldTokens, newTokens);

      let html = '';
      for (const op of ops) {
        const value = Array.isArray(op.value) ? op.value.join('') : String(op.value ?? '');
        const escaped = this.escapeHtml(value);
        const escapedWithParagraphSpacing = escaped.replace(/&lt;\/p&gt;/g, '&lt;/p&gt;\n\n');
        if (!op.added && !op.removed) {
          html += escapedWithParagraphSpacing;
        } else if (op.removed) {
          html += `<span class="debug-removed">${escaped}</span>`;
        } else if (op.added) {
          html += `<span class="debug-added">${escapedWithParagraphSpacing}</span>`;
        }
      }

      return html;
    },

    buildVisualHtml(block) {
      return this.wrapDiffIntoParagraphs(this.buildRenderedDiffHtml(block));
    },

    buildDebugDump(block, blockLabel) {
      const lines = [];
      const push = (title, body) => {
        lines.push(`\n--- ${title} ---\n`);
        lines.push(body === undefined || body === null ? String(body) : String(body));
      };

      const changeId = this.currentChange?.id ?? '—';
      const resource = `${this.resourceName ?? '—'} / ${this.resourceId ?? '—'}`;
      push(
        'Контекст',
        [
          `Время дампа: ${new Date().toISOString()}`,
          `Ресурс: ${resource}`,
          `change.id: ${changeId}`,
          `Блок (ключ): ${blockLabel ?? '—'}`,
          `block.type: ${block?.type ?? '—'}`,
        ].join('\n')
      );

      const rawOld = this.extractTextHtml(block?.old);
      const rawNew = this.extractTextHtml(block?.new);
      push('extractTextHtml(block.old) — сырой JSON.text / до', rawOld);
      push('extractTextHtml(block.new) — сырой JSON.text / после', rawNew);

      const oldHtml = this.sanitizeHtmlForPreview(rawOld);
      const newHtml = this.sanitizeHtmlForPreview(rawNew);
      push('sanitizeHtmlForPreview(старое)', oldHtml);
      push('sanitizeHtmlForPreview(новое)', newHtml);

      let oldVis = '';
      let newVis = '';
      try {
        oldVis = this.htmlToVisualText(oldHtml);
        newVis = this.htmlToVisualText(newHtml);
      } catch (e) {
        oldVis = `[ошибка htmlToVisualText] ${e.message}`;
        newVis = oldVis;
      }
      push('htmlToVisualText(старое) — плоский текст по блокам', oldVis);
      push('htmlToVisualText(новое)', newVis);

      let oldBlockDump = '';
      let newBlockDump = '';
      try {
        oldBlockDump = JSON.stringify(this.htmlToVisualBlocks(oldHtml), null, 2);
        newBlockDump = JSON.stringify(this.htmlToVisualBlocks(newHtml), null, 2);
      } catch (e) {
        oldBlockDump = `[ошибка] ${e.message}`;
        newBlockDump = oldBlockDump;
      }
      push('htmlToVisualBlocks (старое) — text + kind + diffKey для LCS', oldBlockDump);
      push('htmlToVisualBlocks (новое)', newBlockDump);

      let renderedDiff = '';
      let wrappedVisual = '';
      try {
        renderedDiff = this.buildRenderedDiffHtml(block);
      } catch (e) {
        renderedDiff = `[ошибка buildRenderedDiffHtml] ${e.message}\n${e.stack || ''}`;
      }
      try {
        wrappedVisual = this.wrapDiffIntoParagraphs(renderedDiff);
      } catch (e) {
        wrappedVisual = `[ошибка wrapDiffIntoParagraphs] ${e.message}\n${e.stack || ''}`;
      }
      push('buildRenderedDiffHtml (внутренний HTML диффа, до обёртки в <p>)', renderedDiff);
      push('wrapDiffIntoParagraphs(...) — то же, что уходит в v-html визуала', wrappedVisual);

      let rawHtmlDiff = '';
      try {
        rawHtmlDiff = this.buildRawHtmlDiff(block);
      } catch (e) {
        rawHtmlDiff = `[ошибка buildRawHtmlDiff] ${e.message}`;
      }
      push('buildRawHtmlDiff — HTML-вкладка (escaped + diff)', rawHtmlDiff);

      return lines.join('\n').trim();
    },

    isDebugCopied(blockId) {
      return Boolean(this.copiedDebugBlocks[this.getBlockKey(blockId)]);
    },

    copyPostHistoryDebug(block, blockId) {
      const text = this.buildDebugDump(block, blockId);
      const key = this.getBlockKey(blockId);

      const done = () => {
        this.copiedDebugBlocks = { ...this.copiedDebugBlocks, [key]: Date.now() };
        setTimeout(() => {
          if (this.copiedDebugBlocks[key]) {
            const next = { ...this.copiedDebugBlocks };
            delete next[key];
            this.copiedDebugBlocks = next;
          }
        }, 2500);
      };

      if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(() => {
          if (this.copyWithTextareaFallback(text)) {
            done();
          }
        });
        return;
      }
      if (this.copyWithTextareaFallback(text)) {
        done();
      }
    },

    buildPlainWordDiff(change) {
      const oldValue = String(change?.old ?? '');
      const newValue = String(change?.new ?? '');
      return this.buildPlainWordDiffFromValues(oldValue, newValue);
    },

    buildPlainWordDiffFromValues(oldValue, newValue) {
      const oldTokens = this.tokenizeWithSpaces(oldValue);
      const newTokens = this.tokenizeWithSpaces(newValue);
      const ops = diffArrays(oldTokens, newTokens);

      let html = '';
      for (const op of ops) {
        const value = Array.isArray(op.value) ? op.value.join('') : '';
        const escaped = this.escapeHtml(value);

        if (!op.added && !op.removed) {
          html += escaped;
        } else if (op.removed) {
          html += `<span class="plain-diff-removed">${escaped}</span>`;
        } else if (op.added) {
          html += `<span class="plain-diff-added">${escaped}</span>`;
        }
      }

      return html;
    },

    extractPlainBlockValue(value) {
      const normalized = this.normalizeForDisplay(value);
      if (normalized && typeof normalized === 'object') {
        if (typeof normalized.text === 'string') {
          return normalized.text;
        }
      }
      return typeof normalized === 'string' ? normalized : this.formatJson(value);
    },

    copyMarkup(block, blockId) {
      const html = this.debugHtml(block?.new);
      const blockKey = this.getBlockKey(blockId);

      if (!html) {
        return;
      }

      if (navigator?.clipboard?.writeText) {
        navigator.clipboard
          .writeText(html)
          .then(() => {
            this.markCopied(blockKey);
          })
          .catch(() => {
            const copied = this.copyWithTextareaFallback(html);
            if (copied) {
              this.markCopied(blockKey);
            }
          });
        return;
      }

      const copied = this.copyWithTextareaFallback(html);
      if (copied) {
        this.markCopied(blockKey);
      }
    },

    copyWithTextareaFallback(text) {
      const textarea = document.createElement('textarea');
      textarea.value = String(text ?? '');
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      textarea.style.pointerEvents = 'none';
      document.body.appendChild(textarea);
      textarea.focus();
      textarea.select();

      try {
        return document.execCommand('copy');
      } finally {
        document.body.removeChild(textarea);
      }
    },

    markCopied(blockKey) {
      this.copiedBlocks = {
        ...this.copiedBlocks,
        [blockKey]: Date.now(),
      };

      setTimeout(() => {
        if (this.copiedBlocks[blockKey]) {
          this.copiedBlocks = {
            ...this.copiedBlocks,
            [blockKey]: 0,
          };
        }
      }, 2000);
    },

    isCopied(blockKey) {
      return Boolean(this.copiedBlocks[blockKey]);
    },

    getBlockKey(blockId) {
      const changeId = this.currentChange?.id || 'no-change';
      return `${changeId}:${blockId}`;
    },

    setBlockViewMode(blockId, mode) {
      this.blockViewModes = {
        ...this.blockViewModes,
        [blockId]: mode,
      };
    },

    isVisualMode(blockId) {
      return (this.blockViewModes[blockId] || 'visual') === 'visual';
    },

    buildDiffIframeDoc(diffHtml) {
      const blockHtml = this.wrapDiffIntoParagraphs(diffHtml);
      return `<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewi-Regular/RFDewi-Regular.woff2') format('woff2'),
             url('/fonts/RFDewi-Regular/RFDewi-Regular.woff') format('woff');
        font-weight: 200;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewi-Bold/RFDewi-Bold.woff2') format('woff2'),
             url('/fonts/RFDewi-Bold/RFDewi-Bold.woff') format('woff');
        font-weight: 300;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewiCondensed-Regular/RFDewiCondensed-Regular.woff2') format('woff2'),
             url('/fonts/RFDewiCondensed-Regular/RFDewiCondensed-Regular.woff') format('woff');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewiCondensed-Semibold/RFDewiCondensed-Semibold.woff2') format('woff2'),
             url('/fonts/RFDewiCondensed-Semibold/RFDewiCondensed-Semibold.woff') format('woff');
        font-weight: 500;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewiCondensed-Bold/RFDewiCondensed-Bold.woff2') format('woff2'),
             url('/fonts/RFDewiCondensed-Bold/RFDewiCondensed-Bold.woff') format('woff');
        font-weight: 600;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewiCondensed-Ultrabold/RFDewiCondensed-Ultrabold.woff2') format('woff2'),
             url('/fonts/RFDewiCondensed-Ultrabold/RFDewiCondensed-Ultrabold.woff') format('woff');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
      }
      @font-face {
        font-family: 'RF Dewi';
        src: url('/fonts/RFDewiExpanded-Black/RFDewiExpanded-Black.woff2') format('woff2'),
             url('/fonts/RFDewiExpanded-Black/RFDewiExpanded-Black.woff') format('woff');
        font-weight: 900;
        font-style: normal;
        font-display: swap;
      }
      body {
        margin: 0;
        padding: 10px 25px;
        font-family: 'RF Dewi', ui-sans-serif, system-ui, sans-serif;
        font-size: 0.9rem;
        font-weight: 200;
        line-height: 1.25rem;
        -webkit-font-smoothing: antialiased;
        color: #333;
        white-space: pre-wrap;
        word-break: break-word;
      }
      body * {
        font-family: 'RF Dewi', ui-sans-serif, system-ui, sans-serif;
        -webkit-font-smoothing: antialiased;
      }
      p, li, blockquote {
        font-size: 0.9rem;
        font-weight: 200;
        line-height: 1.25rem;
        color: #333;
      }
      p, h1, h2, h3, h4, h5, h6, blockquote, ul, ol, hr {
        margin-bottom: 1.5rem;
      }
      h1, h2, h3, h4, h5, h6 {
        color: #000;
      }
      h3 {
        font-size: 1.25rem !important;
        font-weight: 300 !important;
        line-height: 1.3;
      }
      h3.outline-heading {
        display: block;
        position: relative;
        padding-top: 0.25rem;
        width: fit-content;
        max-width: 100%;
        margin-bottom: 1.5rem;
        border-top: 0.5rem solid var(--gray-dark-color, #333);
        font-weight: 700 !important;
        color: var(--gray-dark-color, #333);
        font-size: 1.625rem !important;
        line-height: 1.875rem;
        -webkit-font-smoothing: antialiased;
      }
      h4 {
        font-size: 1.125rem !important;
        font-weight: 300 !important;
        line-height: 1.3;
      }
      h5 {
        font-size: 1rem !important;
        font-weight: 300 !important;
        line-height: 1.3;
      }
      b, strong {
        font-weight: 300;
      }
      img, video, iframe { max-width: 100%; height: auto; }
      a {
        font-weight: 200;
        color: #e54839;
        transition: all 0.2s ease-in-out;
      }
      a:hover { text-decoration: underline; }
      blockquote {
        font-style: normal !important;
        padding-left: 1.25rem;
        border-left: 1px solid #e54839 !important;
        background: none !important;
      }
      ul {
        list-style: none;
        padding-left: 0;
      }
      ul li {
        position: relative;
        padding-left: 1.25rem;
        margin-bottom: 0.75rem;
      }
      ul li::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0.5em;
        width: 6px;
        height: 6px;
        background-color: #e54839;
        border-radius: 50%;
      }
      ol {
        list-style: none;
        padding-left: 0;
        counter-reset: ol-counter;
      }
      ol li {
        position: relative;
        padding-left: 1.75rem;
        margin-bottom: 0.75rem;
        counter-increment: ol-counter;
      }
      ol li::before {
        content: counter(ol-counter) '.';
        position: absolute;
        left: 0;
        top: 0;
        color: #e54839;
        font-weight: 300;
      }
      .ck-termin-highlight {
        display: inline;
        cursor: pointer;
        background-color: #ebebeb;
        color: #e54839;
        padding: 0 0.375rem 0.125rem 0.375rem;
        border-radius: 2px;
        border-bottom: 2px dotted #e54839;
      }
      hr {
        background-color: #eee;
        border: none;
        height: 5px;
        padding: 0;
        display: block;
        clear: both;
      }
      .diff-removed {
        background: #ffd7d7;
        color: #7f1d1d;
        text-decoration: line-through;
        border-radius: 2px;
        padding: 0 1px;
      }
      .diff-added {
        background: #b8f0c8;
        color: #333;
        border-radius: 2px;
        padding: 0 1px;
      }
      .diff-added .is-outline-heading,
      .diff-added .is-heading {
        color: #333 !important;
      }
      .diff-added .is-outline-heading {
        border-top-color: #27ae60;
      }
      .diff-removed .is-outline-heading,
      .diff-removed .is-heading {
        color: inherit !important;
      }
      .diff-removed .is-outline-heading {
        border-top-color: #e74c3c;
      }
      .diff-format-change {
        background: #e9dfd2;
        color: #5c4732;
        border-radius: 2px;
        padding: 0 1px;
      }
      .is-heading {
        font-weight: 700 !important;
        color: var(--gray-dark-color, #333);
        font-size: 1.625rem;
        line-height: 1.875rem;
        -webkit-font-smoothing: antialiased;
      }
      .is-outline-heading {
        display: inline-block;
        position: relative;
        padding-top: 0.25rem;
        width: fit-content;
        max-width: 100%;
        border-top: 0.5rem solid var(--gray-dark-color, #333);
        font-weight: 700 !important;
        color: var(--gray-dark-color, #333);
        font-size: 1.625rem !important;
        line-height: 1.875rem;
        -webkit-font-smoothing: antialiased;
      }
      .is-quote {
        display: block;
        font-style: normal !important;
        padding-left: 1.25rem;
        border-left: 1px solid #e54839;
        margin: 0.25rem 0;
      }
      .diff-format-change a {
        color: inherit;
        text-decoration: underline;
      }
      .text-muted {
        color: #777;
      }
      #__diff-root {
        display: flow-root;
      }
    </style>
  </head>
  <body><div id="__diff-root">${blockHtml}</div></body>
</html>`;
    },

    wrapDiffIntoParagraphs(diffHtml) {
      const source = String(diffHtml ?? '').trim();
      if (!source) {
        return '<p></p>';
      }

      const fullSpanMatch = source.match(/^<span class="([^"]+)">([\s\S]*)<\/span>$/);
      if (fullSpanMatch) {
        const spanClass = fullSpanMatch[1];
        const inner = fullSpanMatch[2];
        return inner
          .split(/\n{2,}/)
          .map((chunk) => chunk.trim())
          .filter(Boolean)
          .map((chunk) => `<p><span class="${spanClass}">${chunk.replace(/\n/g, '<br>')}</span></p>`)
          .join('');
      }

      return source
        .split(/\n{2,}/)
        .map((chunk) => chunk.trim())
        .filter(Boolean)
        .map((chunk) => `<p>${chunk.replace(/\n/g, '<br>')}</p>`)
        .join('');
    },

    normalizeForDisplay(value) {
      const parsed = this.parseJsonString(value);
      if (parsed !== value) {
        return this.normalizeForDisplay(parsed);
      }

      if (Array.isArray(value)) {
        return value.map((item) => this.normalizeForDisplay(item));
      }

      if (value && typeof value === 'object') {
        return Object.fromEntries(
          Object.entries(value).map(([key, item]) => [key, this.normalizeForDisplay(item)])
        );
      }

      return value;
    },

    formatJson(value) {
      const normalized = this.normalizeForDisplay(value);

      if (typeof normalized === 'string') {
        return normalized;
      }

      return JSON.stringify(normalized, null, 2);
    }
  },

  mounted() {
    this.loadChanges();
  }
}
</script>

<style scoped>
/* Как в остальной Nova: RF Dewi, 0.9rem / 200, акцент 300 */
.history-viewer {
  font-family: 'RF Dewi', ui-sans-serif, system-ui, sans-serif;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.history-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 4px;
  margin-bottom: 38px;
}

.history-scroll-list {
  width: 100%;
  min-width: 0;
  margin: 0;
  padding: 6px 4px 10px;
  background: #f9fafb;
  border: 1px solid #f3f4f6;
  border-radius: 8px;
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  /* scroll: полоса всегда занимает место внизу */
  overflow-x: scroll;
  overflow-y: hidden;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;
  scrollbar-gutter: stable;
  scrollbar-width: auto;
  /* Firefox: ползунок / дорожка (активное состояние ползунка там не стилизуется) */
  scrollbar-color: #9ca3af #e5e7eb;
}

.history-scroll-list::-webkit-scrollbar {
  -webkit-appearance: none;
  appearance: none;
  height: 11px;
}

.history-scroll-list::-webkit-scrollbar-track {
  margin: 0 2px;
  background: #e5e7eb;
  border-radius: 0;
  border: 1px solid #d1d5db;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.history-scroll-list::-webkit-scrollbar-thumb {
  background: #9ca3af;
  border-radius: 0;
  border: 1px solid #6b7280;
}

.history-scroll-list::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
  border-color: #4b5563;
}

.history-scroll-list::-webkit-scrollbar-thumb:active {
  background: #e54839;
  border-color: #c73a2d;
}

.history-item {
  flex-shrink: 0;
  margin: 0;
  padding: 7px 12px;
  background: #fff;
  color: #4b5563;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
  font-size: 0.875rem;
  font-weight: 200;
  line-height: 1.25rem;
  font-variant-numeric: tabular-nums;
  font-feature-settings: 'tnum' 1;
}

.history-item:focus-visible {
  outline: 2px solid #e54839;
  outline-offset: 2px;
}

.history-item.active {
  font-weight: 300;
  color: #111827;
}

.history-date-list .history-item:hover:not(.active) {
  background: #f9fafb;
  border-color: #d1d5db;
}

.history-date-list .history-item.active {
  background: #e54839;
  border-color: #e54839;
  color: #fff;
  box-shadow: 0 1px 2px rgba(229, 72, 57, 0.25);
}

.history-time-list .history-item:hover:not(.active) {
  background: #f9fafb;
  border-color: #d1d5db;
}

.history-time-list .history-item.active {
  background: #fff;
  border-color: #e54839;
  color: #111827;
  box-shadow: 0 0 0 1px #e54839;
}

.change-cards {
  padding-top: 14px;
}

.history-top-bar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: start;
  gap: 12px;
  margin-bottom: 6px;
}

.history-empty-message {
  margin: 0 0 14px;
  padding: 14px 16px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.35;
}

.history-post-title {
  margin: 0 0 18px;
  min-width: 0;
  overflow-wrap: anywhere;
}

.history-top-actions {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0;
  flex-shrink: 0;
}

.history-edit-link {
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  color: #fff;
  text-decoration: none;
  border: 1px solid #111827;
  border-radius: 6px;
  padding: 4px 10px;
  background: #111827;
}

.history-edit-link:hover {
  background: #000;
  border-color: #000;
  text-decoration: none;
}

.history-meta-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 12px 14px;
}

.history-meta-top {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}

.status-chip {
  display: inline-block;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 0.9rem;
  font-weight: 300;
  line-height: 1.25rem;
}

.status-chip--created {
  background: #e6ffed;
  color: #14532d;
}

.status-chip--updated {
  background: #fff3cd;
  color: #92400e;
}

.status-chip--deleted {
  background: #fdecea;
  color: #7f1d1d;
}

.history-meta-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
}

.history-meta-row + .history-meta-row {
  margin-top: 4px;
}

.history-meta-label {
  color: #6b7280;
  min-width: 50px;
}

.history-meta-value {
  color: #111827;
  word-break: break-word;
}

.change-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
  padding: 15px;
  margin-bottom: 20px;
}

.content-field {
  margin-bottom: 20px;
}

.field-title {
  font-size: 1rem;
  font-weight: 300;
  line-height: 1.3;
  margin-bottom: 10px;
  color: #171717;
}

.block-separator {
  height: 10px;
}

.nested-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
  padding: 15px;
  margin-top: 10px;
}

.text-unified-wrapper {
  padding-left: 0;
}

.view-mode-toggle {
  display: inline-flex;
  gap: 8px;
  margin-bottom: 8px;
  width: 100%;
}

.view-mode-btn {
  border: 1px solid #d1d5db;
  background: #f9fafb;
  color: #374151;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  font-family: inherit;
  cursor: pointer;
}

.view-mode-btn.active {
  background: #2563eb;
  border-color: #2563eb;
  color: #fff;
}

.view-mode-toggle .view-mode-btn:last-child {
  margin-left: auto;
}

.html-preview-render {
  margin-top: 8px;
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
  overflow: hidden;
  padding: 10px 25px;
  color: #333;
  font-family: 'RF Dewi', ui-sans-serif, system-ui, sans-serif;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  -webkit-font-smoothing: antialiased;
}

.html-preview-render :deep(p),
.html-preview-render :deep(li),
.html-preview-render :deep(blockquote) {
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  color: #333;
}

.html-preview-render :deep(p),
.html-preview-render :deep(h1),
.html-preview-render :deep(h2),
.html-preview-render :deep(h3),
.html-preview-render :deep(h4),
.html-preview-render :deep(h5),
.html-preview-render :deep(h6),
.html-preview-render :deep(blockquote),
.html-preview-render :deep(ul),
.html-preview-render :deep(ol),
.html-preview-render :deep(hr) {
  margin-bottom: 1.5rem;
}

.html-preview-render :deep(a) {
  color: #e54839;
}

.html-preview-render :deep(strong),
.html-preview-render :deep(b) {
  font-weight: 300;
  font-size: inherit;
  line-height: inherit;
}

.html-preview-render :deep(.diff-removed) {
  background: #ffd7d7;
  color: #7f1d1d;
  text-decoration: line-through;
  border-radius: 2px;
  padding: 0 1px;
}

.html-preview-render :deep(.diff-added) {
  background: #b8f0c8;
  color: #333;
  border-radius: 2px;
  padding: 0 1px;
}

.html-preview-render :deep(.diff-added .is-outline-heading),
.html-preview-render :deep(.diff-added .is-heading) {
  color: #333 !important;
}
.html-preview-render :deep(.diff-added .is-outline-heading) {
  border-top-color: #27ae60;
}

.html-preview-render :deep(.diff-removed .is-outline-heading),
.html-preview-render :deep(.diff-removed .is-heading) {
  color: inherit !important;
}
.html-preview-render :deep(.diff-removed .is-outline-heading) {
  border-top-color: #e74c3c;
}

.html-preview-render :deep(.diff-format-change) {
  background: #e9dfd2;
  color: #5c4732;
  border-radius: 2px;
  padding: 0 1px;
}

.html-preview-render :deep(.is-heading:not(.is-outline-heading)) {
  font-weight: 700 !important;
  color: var(--gray-dark-color, #333);
  font-size: 1.625rem;
  line-height: 1.875rem;
  -webkit-font-smoothing: antialiased;
}

.html-preview-render :deep(.is-outline-heading) {
  display: inline-block;
  position: relative;
  padding-top: 0.25rem;
  width: fit-content;
  max-width: 100%;
  border-top: 0.5rem solid var(--gray-dark-color, #333);
  font-weight: 700 !important;
  color: var(--gray-dark-color, #333);
  font-size: 1.625rem !important;
  line-height: 1.875rem;
  -webkit-font-smoothing: antialiased;
}

.html-preview-render :deep(.is-quote) {
  display: block;
  font-style: normal !important;
  padding-left: 1.25rem;
  border-left: 1px solid #e54839;
  margin: 0.25rem 0;
}

.debug-html-block {
  margin-top: 8px;
}

.debug-html-title {
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  color: #6b7280;
  margin: 6px 0 4px;
}

.post-history-debug {
  margin-top: 12px;
  border: 1px dashed #cbd5e1;
  border-radius: 6px;
  padding: 8px 10px;
  background: #f8fafc;
}

.post-history-debug__summary {
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 300;
  color: #475569;
  user-select: none;
}

.post-history-debug__textarea {
  display: block;
  width: 100%;
  margin-top: 8px;
  box-sizing: border-box;
  padding: 8px 10px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.75rem;
  line-height: 1.35;
  color: #0f172a;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  resize: vertical;
  min-height: 200px;
}

.post-history-debug__copy {
  margin-top: 8px;
  padding: 6px 12px;
  font-size: 0.8rem;
  font-weight: 300;
  color: #334155;
  background: #e2e8f0;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.post-history-debug__copy:hover {
  background: #cbd5e1;
}

.debug-html-pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
  font-family: 'RF Dewi', ui-sans-serif, system-ui, sans-serif;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  padding: 25px;
}

.debug-html-pre :deep(.debug-removed) {
  background: #ffd7d7;
  color: #7f1d1d;
}

.debug-html-pre :deep(.debug-added) {
  background: #b8f0c8;
  color: #333;
}

.text-muted {
  color: #777;
}

.plain-unified-wrapper {
  margin-top: 8px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
  padding: 12px;
}

.plain-diff-value {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.9rem;
  font-weight: 200;
  line-height: 1.25rem;
}

.plain-diff-value :deep(.plain-diff-removed) {
  background: #ffd7d7;
  color: #7f1d1d;
  text-decoration: line-through;
  border-radius: 2px;
  padding: 0 1px;
}

.plain-diff-value :deep(.plain-diff-added) {
  background: #b8f0c8;
  color: #333;
  border-radius: 4px;
  padding: 3px 6px;
}

.deleted-card {
  background: #fdecea;
  border-left: 5px solid #e74c3c;
  padding: 10px;
  border-radius: 4px;
}

.added-card {
  background: #e6ffed;
  border-left: 5px solid #27ae60;
  padding: 10px;
  border-radius: 4px;
}

.updated-card {
  background: #fff3cd;
  border-left: 5px solid #f1c40f;
  padding: 10px;
  border-radius: 4px;
}

.flex-container {
  display: flex;
  justify-content: space-between;
  gap: 20px;
}

.old-value-block,
.new-value-block {
  width: 48%;
  background: #fff;
  padding: 10px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
}

.old-value-title {
  display: block;
  font-weight: 300;
  font-size: 0.9rem;
  line-height: 1.25rem;
  color: #e74c3c;
  margin-bottom: 5px;
}

.new-value-title {
  display: block;
  font-weight: 300;
  font-size: 0.9rem;
  line-height: 1.25rem;
  color: #27ae60;
  margin-bottom: 5px;
}
</style>
