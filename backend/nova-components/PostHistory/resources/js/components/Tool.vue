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
              <div v-if="block.old && !block.new" class="deleted-card">
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

export default {
  props: ['resourceName', 'resourceId', 'panel'],

  data() {
    return {
      postTitle: '',
      changes: [],
      currentIndex: 0,
      currentChange: null,
    };
  },

  methods: {
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

    formatJson(obj) {
      return JSON.stringify(obj, null, 2);
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
