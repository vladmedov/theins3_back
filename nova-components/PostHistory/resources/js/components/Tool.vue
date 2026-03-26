<template>
  <div class="history-viewer">
    <!-- Горизонтальный список дат с плавным переключением -->
    <div class="history-scroll-container">
      <div class="history-scroll-list">
        <button 
          v-for="(change, index) in changes" 
          :key="change.id"
          :class="['history-item', { active: index === currentIndex }]"
          @click="selectChange(index)"
        >
          {{ new Date(change.created_at).toLocaleString('ru-RU', {
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              second: '2-digit'
            }).replace(',', '')
          }}
        </button>
      </div>
    </div>

    <!-- Карточки изменений -->
    <div v-if="currentChange" class="change-details">
      <span :class="['status', currentChange.status]">
        <h2 class="font-normal text-lg md:text-lg">{{ getStatusText(currentChange.status) }}</h2>
        <p>Кем: {{ currentChange.user_fullname }}</p>
      </span>
      <div class="change-cards">
        <div 
          v-for="(change, field) in currentChange.changes" 
          :key="field"
          class="change-card"
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
                    :class="{ active: isVisualMode(blockId) }"
                    @click="setBlockViewMode(blockId, 'visual')"
                  >
                    Визуальный
                  </button>
                  <button
                    type="button"
                    class="view-mode-btn"
                    :class="{ active: !isVisualMode(blockId) }"
                    @click="setBlockViewMode(blockId, 'html')"
                  >
                    HTML
                  </button>
                </div>

                <iframe
                  v-if="isVisualMode(blockId)"
                  class="html-preview-frame unified-frame"
                  sandbox
                  :srcdoc="buildDiffIframeDoc(buildRenderedDiffHtml(block))"
                ></iframe>
                <div v-else class="debug-html-block">
                  <pre class="debug-html-pre" v-html="buildRawHtmlDiff(block)"></pre>
                </div>
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
          <div class="change-content" v-else>
            <div v-if="change.old && !change.new" class="deleted-card">
              <div>{{ change.old }}</div>
            </div>
            <div v-else-if="change.new && !change.old" class="added-card">
              <div>{{ change.new }}</div>
            </div>
            <div v-else class="updated-card">
              <div class="flex-container">
                <div class="old-value-block">
                  <span class="old-value-title">Было:</span>
                  <div class="deleted-value">{{ change.old }}</div>
                </div>
                <div class="new-value-block">
                  <span class="new-value-title">Стало:</span>
                  <div class="added-value">{{ change.new }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import { diffWordsWithSpace } from 'diff';

export default {
  props: ['resourceName', 'resourceId', 'panel'],

  data() {
    return {
      postTitle: '',
      changes: [],
      currentIndex: 0,
      currentChange: null,
      wordDiffOptions: null,
      blockViewModes: {},
    };
  },

  methods: {
    isTextBlock(block) {
      return block?.type === 'text';
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
      this.changes = data.changes.map(change => {
        change.changes = this.parseChanges(change.changes);
        return change;
      });
      this.currentIndex = 0;
      this.loadCurrentChange();
    },

    async loadCurrentChange() {
      const changeId = this.changes[this.currentIndex].id;
      const { data } = await axios.get(`/nova-vendor/post-history/${this.resourceId}/change/${changeId}`);
      this.currentChange = data;
      this.currentChange.changes = this.parseChanges(this.currentChange.changes);
    },

    selectChange(index) {
      this.currentIndex = index;
      this.loadCurrentChange();
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

    extractTextHtml(value) {
      const normalized = this.normalizeForDisplay(value);
      if (normalized && typeof normalized === 'object' && typeof normalized.text === 'string') {
        return normalized.text;
      }
      return '';
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

    getFormattingSignature(textNode) {
      const marks = [];
      let node = textNode.parentElement;

      while (node) {
        const tag = node.tagName?.toLowerCase();
        if (tag === 'strong' || tag === 'b' || tag === 'em' || tag === 'i' || tag === 'u') {
          marks.push(tag);
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
      const strong = new Set();

      for (let i = 0; i < newTokens.length; i += 1) {
        const signature = newTokens[i].signature || '';
        if (/(^|\|)(strong|b)($|\|)/.test(signature)) {
          strong.add(i);
        }
      }

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

      return { changed, strong };
    },

    renderTokenWithStyles(token, globalIndex, changedTokenIndices, strongTokenIndices, baseClass = '') {
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

      const escaped = this.escapeHtml(token);
      if (classes.length === 0) {
        return escaped;
      }

      return `<span class="${classes.join(' ')}">${escaped}</span>`;
    },

    renderEqualChunkWithMarkupDiff(text, changedTokenIndices, strongTokenIndices, startIndex) {
      const tokens = this.tokenizeWithSpaces(text);
      let rendered = '';

      for (let i = 0; i < tokens.length; i += 1) {
        const token = tokens[i];
        const globalIndex = startIndex + i;
        rendered += this.renderTokenWithStyles(
          token,
          globalIndex,
          changedTokenIndices,
          strongTokenIndices
        );
      }

      return {
        html: rendered,
        nextIndex: startIndex + tokens.length,
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

        const oldSignature = oldByText.map((item) => item.href).sort().join('|');
        const newSignature = newByText.map((item) => item.href).sort().join('|');
        if (oldSignature === newSignature) {
          continue;
        }

        const escapedText = this.escapeHtml(text);
        const replacement = newByText.length > 0
          ? `<span class="diff-format-change">${this.renderFormatLink(newByText[0])}</span>`
          : `<span class="diff-format-change">${escapedText}</span>`;

        result = this.replaceFirstLiteral(result, escapedText, replacement);
      }

      return result;
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
      const oldText = this.htmlToDisplayText(oldHtml);
      const newText = this.htmlToDisplayText(newHtml);
      const tokenSets = this.buildFormatTokenSets(oldHtml, newHtml);
      const changedMarkupTokenIndices = tokenSets.changed;
      const strongTokenIndices = tokenSets.strong;
      const ops = diffWordsWithSpace(
        String(oldText ?? ''),
        String(newText ?? ''),
        this.getWordDiffOptions()
      );

      let html = '';
      let newTokenIndex = 0;
      for (let i = 0; i < ops.length; i += 1) {
        const op = ops[i];
        const nextOp = ops[i + 1];
        const value = op?.value ?? '';
        const displayText = this.htmlToDisplayText(value);

        // If only formatting changed for the same visible text, use neutral highlight.
        if (op?.removed && nextOp?.added) {
          const removedText = this.htmlToPlainText(op.value);
          const addedText = this.htmlToPlainText(nextOp.value);
          if (removedText && removedText === addedText) {
            html += `<span class="diff-format-change">${this.escapeHtml(addedText)}</span>`;
            i += 1;
            continue;
          }
        }

        if (!op.added && !op.removed) {
          const rendered = this.renderEqualChunkWithMarkupDiff(
            displayText,
            changedMarkupTokenIndices,
            strongTokenIndices,
            newTokenIndex
          );
          html += rendered.html;
          newTokenIndex = rendered.nextIndex;
        } else if (op.removed) {
          const removedText = this.htmlToPlainText(value);
          if (removedText) {
            html += `<span class="diff-removed">${this.escapeHtml(removedText)}</span>`;
          }
        } else if (op.added) {
          const addedText = this.htmlToPlainText(value);
          if (addedText) {
            const tokens = this.tokenizeWithSpaces(addedText);
            for (let ti = 0; ti < tokens.length; ti += 1) {
              const token = tokens[ti];
              html += this.renderTokenWithStyles(
                token,
                newTokenIndex + ti,
                changedMarkupTokenIndices,
                strongTokenIndices,
                'diff-added'
              );
            }
            newTokenIndex += tokens.length;
          }
        }
      }

      const withLinkDiff = this.applyAnchorMarkupDiffs(html, oldHtml, newHtml);
      return withLinkDiff || '<span class="text-muted">Без изменений</span>';
    },

    debugHtml(value) {
      return this.decodeHtmlEntitiesDeep(this.extractTextHtml(value));
    },

    buildRawHtmlDiff(block) {
      const oldHtml = this.debugHtml(block.old);
      const newHtml = this.debugHtml(block.new);
      const ops = diffWordsWithSpace(String(oldHtml ?? ''), String(newHtml ?? ''), this.getWordDiffOptions());

      let html = '';
      for (const op of ops) {
        const escaped = this.escapeHtml(op.value ?? '')
          .replace(/&lt;\/p&gt;/g, '&lt;/p&gt;\n\n');
        if (!op.added && !op.removed) {
          html += escaped;
        } else if (op.removed) {
          html += `<span class="debug-removed">${escaped}</span>`;
        } else if (op.added) {
          html += `<span class="debug-added">${escaped}</span>`;
        }
      }

      return html;
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
      return `<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      body {
        margin: 0;
        padding: 10px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        line-height: 1.5;
        color: #1f2937;
        word-break: break-word;
      }
      p { margin: 0 0 10px; }
      img, video, iframe { max-width: 100%; height: auto; }
      a { color: #2563eb; }
      .diff-removed {
        background: #ffd7d7;
        color: #7f1d1d;
        text-decoration: line-through;
        border-radius: 2px;
        padding: 0 1px;
      }
      .diff-added {
        background: #d7ffe2;
        color: #14532d;
        border-radius: 2px;
        padding: 0 1px;
      }
      .diff-format-change {
        background: #e9dfd2;
        color: #5c4732;
        border-radius: 2px;
        padding: 0 1px;
      }
      .is-strong {
        font-weight: 700;
      }
      .diff-format-change a {
        color: inherit;
        text-decoration: underline;
      }
      .text-muted {
        color: #777;
      }
    </style>
  </head>
  <body>${diffHtml}</body>
</html>`;
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
.history-scroll-container {
  padding-top: 20px;
  padding-bottom: 10px;
}

.history-scroll-list {
  border-left: 1px solid #999;
  border-right: 1px solid #999;
  padding: 0 10px;
  width: 100%;
  display: inline-flex;
  gap: 10px;
  overflow-x: auto;
  white-space: nowrap;
  margin-bottom: 20px;
}

.history-item {
  padding: 8px 12px;
  background: #f0f0f0;
  color: #333;
  border: 1px solid #ccc;
  cursor: pointer;
  transition: background 0.3s;
}

.history-item.active {
  background: #2b2b2b;
  color: white;
}

.change-cards {
  padding-top: 20px;
}

.change-card {
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
  padding: 15px;
  margin-bottom: 20px;
}

.field-title {
  font-size: 18px;
  font-weight: bold;
  margin-bottom: 10px;
}

.block-separator {
  height: 10px;
}

.nested-card {
  border-left: 3px solid #ccc;
  padding-left: 10px;
  margin-top: 10px;
}

.text-unified-wrapper {
  padding-left: 0;
}

.view-mode-toggle {
  display: inline-flex;
  gap: 8px;
  margin-bottom: 8px;
}

.view-mode-btn {
  border: 1px solid #d1d5db;
  background: #f9fafb;
  color: #374151;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 12px;
  cursor: pointer;
}

.view-mode-btn.active {
  background: #2563eb;
  border-color: #2563eb;
  color: #fff;
}

.html-preview-frame {
  margin-top: 8px;
  width: 100%;
  min-height: 320px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
}

.unified-frame {
  border-left: 5px solid #3490dc;
}

.debug-html-block {
  margin-top: 8px;
}

.debug-html-title {
  font-size: 12px;
  color: #6b7280;
  margin: 6px 0 4px;
}

.debug-html-pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 13px;
  line-height: 1.5;
  background: #fff;
  border: 1px solid #d1d5db;
  min-height: 320px;
  max-height: 320px;
  overflow: auto;
  border-radius: 4px;
  padding: 10px;
  border-left: 5px solid #3490dc;
}

.debug-html-pre :deep(.debug-removed) {
  background: #ffd7d7;
  color: #7f1d1d;
}

.debug-html-pre :deep(.debug-added) {
  background: #d7ffe2;
  color: #14532d;
}

.text-muted {
  color: #777;
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
  font-weight: bold;
  color: #e74c3c;
  margin-bottom: 5px;
}

.new-value-title {
  display: block;
  font-weight: bold;
  color: #27ae60;
  margin-bottom: 5px;
}
</style>
