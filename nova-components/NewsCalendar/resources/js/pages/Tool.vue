<template>
  <div class="news-calendar-container">
    <h1 class="font-normal text-xl md:text-xl">{{ __('News calendar') }}</h1>
    
    <!-- Фильтры -->
    <div class="filter-container">
      <!-- Тип публикации -->
      <div
        class="searchable-select"
        v-click-outside="() => resourceDropdownOpen = false"
      >
        <div class="searchable-select__input-wrap" @click="resourceDropdownOpen = !resourceDropdownOpen">
          <span class="searchable-select__value" :class="{ 'is-all': !selectedResource }">
            {{ selectedResourceLabel || __('All publications') }}
          </span>
          <span class="searchable-select__arrow">▾</span>
        </div>
        <div v-if="resourceDropdownOpen" class="searchable-select__dropdown">
          <div class="searchable-select__options">
            <div
              class="searchable-select__option searchable-select__option--all"
              :class="{ 'is-selected': selectedResource === '' }"
              @click="selectResource('', '')"
            >{{ __('All publications') }}</div>
            <div
              v-for="resource in resources.post_types"
              :key="resource.value"
              class="searchable-select__option"
              :class="{ 'is-selected': selectedResource === resource.value }"
              @click="selectResource(resource.value, resource.label)"
            >{{ resource.label }}</div>
          </div>
        </div>
      </div>

      <!-- Автор (для всех, кроме opinion) -->
      <div
        v-if="selectedResource !== 'opinion'"
        class="searchable-select"
        v-click-outside="() => authorDropdownOpen = false"
      >
        <div class="searchable-select__input-wrap" @click="openAuthorDropdown">
          <span class="searchable-select__value" :class="{ 'is-all': !selectedAuthor }">
            {{ selectedAuthorLabel || __('All users') }}
          </span>
          <span class="searchable-select__arrow">▾</span>
        </div>
        <div v-if="authorDropdownOpen" class="searchable-select__dropdown">
          <input
            ref="authorSearch"
            v-model="authorSearchQuery"
            class="searchable-select__search"
            :placeholder="__('Search...')"
            @click.stop
          />
          <div class="searchable-select__options">
            <div
              class="searchable-select__option searchable-select__option--all"
              :class="{ 'is-selected': selectedAuthor === '' }"
              @click="selectAuthor('', '')"
            >{{ __('All users') }}</div>
            <div
              v-for="author in filteredAuthors"
              :key="author.id"
              class="searchable-select__option"
              :class="{ 'is-selected': selectedAuthor === author.id }"
              @click="selectAuthor(author.id, author.name)"
            >{{ author.name }}</div>
            <div v-if="filteredAuthors.length === 0" class="searchable-select__empty">
              {{ __('No results') }}
            </div>
          </div>
        </div>
      </div>

      <!-- Колумнист (только для opinion) -->
      <div
        v-if="selectedResource === 'opinion'"
        class="searchable-select"
        v-click-outside="() => columnistDropdownOpen = false"
      >
        <div class="searchable-select__input-wrap" @click="openColumnistDropdown">
          <span class="searchable-select__value" :class="{ 'is-all': !selectedColumnist }">
            {{ selectedColumnistLabel || __('All columnists') }}
          </span>
          <span class="searchable-select__arrow">▾</span>
        </div>
        <div v-if="columnistDropdownOpen" class="searchable-select__dropdown">
          <input
            ref="columnistSearch"
            v-model="columnistSearchQuery"
            class="searchable-select__search"
            :placeholder="__('Search...')"
            @click.stop
          />
          <div class="searchable-select__options">
            <div
              class="searchable-select__option searchable-select__option--all"
              :class="{ 'is-selected': selectedColumnist === '' }"
              @click="selectColumnist('', '')"
            >{{ __('All columnists') }}</div>
            <div
              v-for="columnist in filteredColumnists"
              :key="columnist.id"
              class="searchable-select__option"
              :class="{ 'is-selected': selectedColumnist === columnist.id }"
              @click="selectColumnist(columnist.id, columnist.name)"
            >{{ columnist.name }}</div>
            <div v-if="filteredColumnists.length === 0" class="searchable-select__empty">
              {{ __('No results') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Статистика -->
    <div class="stats-container">
      <span>{{ __('Publications count') }}: {{ totalEvents }}</span> | 
      <span>{{ __('Publication views count') }}: {{ totalViews }}</span>
    </div>

    <div ref="calendar"></div>
  </div>
</template>

<script>
import { Calendar } from '@fullcalendar/core';
import ruLocale from '@fullcalendar/core/locales/ru';
import enLocale from '@fullcalendar/core/locales/en-gb';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import axios from 'axios';
import { Localization } from 'laravel-nova'

export default {
  mixins: [Localization],

  directives: {
    'click-outside': {
      mounted(el, binding) {
        el._clickOutside = (e) => {
          if (!el.contains(e.target)) binding.value(e);
        };
        document.addEventListener('mousedown', el._clickOutside);
      },
      unmounted(el) {
        document.removeEventListener('mousedown', el._clickOutside);
      }
    }
  },

  data() {
    return {
      calendar: null,
      events: [],
      selectedResource: '',
      selectedResourceLabel: '',
      selectedAuthor: '',
      selectedAuthorLabel: '',
      selectedColumnist: '',
      selectedColumnistLabel: '',
      resourceDropdownOpen: false,
      authorDropdownOpen: false,
      columnistDropdownOpen: false,
      authorSearchQuery: '',
      columnistSearchQuery: '',
      resources: {
        post_types: [],
        authors: [],
        columnists: []
      },
      currentStart: null,
      currentEnd: null,
      totalEvents: 0,
      totalViews: 0
    };
  },

  computed: {
    filteredAuthors() {
      const q = this.authorSearchQuery.toLowerCase();
      const list = q
        ? this.resources.authors.filter(a => a.name.toLowerCase().includes(q))
        : this.resources.authors;
      return [...list].sort((a, b) => a.name.localeCompare(b.name));
    },
    filteredColumnists() {
      const q = this.columnistSearchQuery.toLowerCase();
      const list = q
        ? this.resources.columnists.filter(c => c.name.toLowerCase().includes(q))
        : this.resources.columnists;
      return [...list].sort((a, b) => a.name.localeCompare(b.name));
    }
  },

  methods: {
    fetchResources() {
      axios.get('/nova-vendor/news-calendar/resources')
        .then(response => {
          this.resources = response.data;
        });
    },

    selectResource(value, label) {
      this.selectedResource = value;
      this.selectedResourceLabel = label;
      this.selectedAuthor = '';
      this.selectedAuthorLabel = '';
      this.selectedColumnist = '';
      this.selectedColumnistLabel = '';
      this.resourceDropdownOpen = false;
      this.fetchEvents();
    },

    openAuthorDropdown() {
      this.authorDropdownOpen = !this.authorDropdownOpen;
      if (this.authorDropdownOpen) {
        this.authorSearchQuery = '';
        this.$nextTick(() => this.$refs.authorSearch && this.$refs.authorSearch.focus());
      }
    },

    openColumnistDropdown() {
      this.columnistDropdownOpen = !this.columnistDropdownOpen;
      if (this.columnistDropdownOpen) {
        this.columnistSearchQuery = '';
        this.$nextTick(() => this.$refs.columnistSearch && this.$refs.columnistSearch.focus());
      }
    },

    selectAuthor(id, name) {
      this.selectedAuthor = id;
      this.selectedAuthorLabel = name;
      this.authorDropdownOpen = false;
      this.fetchEvents();
    },

    selectColumnist(id, name) {
      this.selectedColumnist = id;
      this.selectedColumnistLabel = name;
      this.columnistDropdownOpen = false;
      this.fetchEvents();
    },

    /**
     * Получение событий и статистики
     */
    fetchEvents(startDate = null, endDate = null) {
      const start = startDate || this.currentStart;
      const end = endDate || this.currentEnd;

      axios.get('/nova-vendor/news-calendar/events', {
        params: {
          resource: this.selectedResource,
          author_id: this.selectedAuthor,
          columnist_id: this.selectedColumnist,
          start: start,
          end: end
        }
      }).then(response => {
        this.events = response.data.events;
        this.calendar.removeAllEvents();
        this.calendar.addEventSource(this.events);

        this.totalEvents = response.data.totalEvents;
        this.totalViews = response.data.totalViews;
      });
    }
  },
  mounted() {
    let currentLocale = document.documentElement.lang || 'en';
    let calendarLocale = currentLocale === 'ru' ? 'ru' : 'en-gb';
    let calendarLocales = currentLocale === 'ru' ? [ruLocale] : [enLocale];

    this.calendar = new Calendar(this.$refs.calendar, {
      plugins: [dayGridPlugin, interactionPlugin],
      initialView: 'dayGridMonth',
      showNonCurrentDates: false,
      events: this.events,
      eventOrder: '-start', // Обратная сортировка по дате начала
      locale: calendarLocale, // Динамическая локаль
      locales: calendarLocales,

      // Обновление при смене месяца
      datesSet: (info) => {
        this.currentStart = info.startStr;
        this.currentEnd = info.endStr;
        this.fetchEvents(info.startStr, info.endStr);
      },

      eventContent: function(arg) {
        // Контейнер для события
        let eventContainer = document.createElement('div');
        eventContainer.style.display = 'flex';
        eventContainer.style.flexDirection = 'column';
        eventContainer.style.alignItems = 'flex-start';
        eventContainer.style.width = '100%';
        eventContainer.style.padding = '2px 5px';

        // Заголовок события
        let eventTitle = document.createElement('div');
        eventTitle.innerHTML = `<strong>${arg.event.title}</strong>`;
        eventTitle.style.display = 'block';
        eventTitle.style.width = '100%';
        eventTitle.style.margin = '0';
        eventTitle.style.padding = '0';
        eventTitle.style.lineHeight = '1.1';
        eventTitle.style.overflow = 'hidden';
        eventTitle.style.textOverflow = 'ellipsis';
        eventTitle.style.whiteSpace = 'nowrap';

        // Имя автора под заголовком с компактным отступом
        let eventAuthor = document.createElement('div');
        eventAuthor.innerHTML = `<small style="color: #666;">${arg.event.extendedProps.author}</small>`;
        eventAuthor.style.display = 'block';
        eventAuthor.style.width = '100%';
        eventAuthor.style.margin = '0';
        eventAuthor.style.padding = '0';
        eventAuthor.style.lineHeight = '1.1';

        // Добавляем в контейнер
        eventContainer.appendChild(eventTitle);
        eventContainer.appendChild(eventAuthor);

        // Возвращаем контейнер в DOM
        return { domNodes: [eventContainer] }
      },
      eventClick: function(info) {
        info.jsEvent.preventDefault(); // Предотвращаем переход по ссылке
        window.open(info.event.url, '_blank'); // Открываем только в новом окне
      }
    });
    this.calendar.render();
    this.fetchResources();
    this.fetchEvents();
  }
}
</script>

<style scoped>
.news-calendar-container {
  padding: 0px;
}

.news-calendar-container h1 {
  margin-bottom: 20px;
}

/* Оформление фильтров */
.filter-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 20px; /* Отступ до статы */
}

.filter-container select {
  padding: 4px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 13px;
  min-width: 130px;
  background: #f8f8f8;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: border 0.3s ease;
}

.filter-container select:focus {
  outline: none;
  border: 1px solid #007bff;
  box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

/* Статистика */
.stats-container {
  margin-bottom: 20px;
  font-size: 14px;
  color: #555;
  background: #f9f9f9;
  padding: 5px 10px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* Searchable select */
.searchable-select {
  position: relative;
  min-width: 200px;
}

.searchable-select__input-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 13px;
  background: #f8f8f8;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  cursor: pointer;
  user-select: none;
  min-height: 29px;
}

.searchable-select__input-wrap:hover {
  border-color: #999;
}

.searchable-select__value {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.searchable-select__arrow {
  margin-left: 6px;
  font-size: 11px;
  color: #888;
}

.searchable-select__dropdown {
  position: absolute;
  top: calc(100% + 2px);
  left: 0;
  z-index: 1000;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 100%;
  max-width: 320px;
}

.searchable-select__search {
  display: block;
  width: 100%;
  padding: 6px 8px;
  border: none;
  border-bottom: 1px solid #eee;
  font-size: 13px;
  outline: none;
  box-sizing: border-box;
}

.searchable-select__options {
  max-height: 220px;
  overflow-y: auto;
}

.searchable-select__option {
  padding: 6px 10px;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
}

.searchable-select__option:hover {
  background: #f0f4ff;
}

.searchable-select__option.is-selected {
  background: #e8eeff;
  font-weight: 600;
}

.searchable-select__empty {
  padding: 8px 10px;
  font-size: 13px;
  color: #999;
  font-style: italic;
}

.searchable-select__option--all {
  font-weight: 600;
  border-bottom: 1px solid #eee;
  margin-bottom: 2px;
}

.searchable-select__value.is-all {
  font-weight: 600;
}
</style>
