<template>
  <div class="news-calendar-container">
    <h1 class="news-calendar-title">{{ __('News calendar') }}</h1>

    <!-- Плашка: месяц слева, фильтры справа (белый фон) -->
    <div class="news-cal-toolbar">
      <div class="news-cal-toolbar__inner">
        <div class="news-cal-toolbar__month" role="group" :aria-label="__('News calendar')">
          <div class="news-cal-nav__field">
            <button
              type="button"
              class="news-cal-nav__btn"
              :aria-label="__('Previous')"
              @click="calendarPrev"
            >‹</button>
            <span class="news-cal-nav__title">{{ calendarViewTitle }}</span>
            <button
              type="button"
              class="news-cal-nav__btn"
              :aria-label="__('Next')"
              @click="calendarNext"
            >›</button>
          </div>
          <button type="button" class="news-cal-nav__today" @click="calendarToday">
            {{ __('News calendar to current month') }}
          </button>
        </div>

        <div class="news-cal-toolbar__filters">
          <!-- Автор (для всех, кроме opinion) -->
          <div
            v-if="selectedResource !== 'opinion'"
            class="searchable-select searchable-select--people"
            v-click-outside="() => authorDropdownOpen = false"
          >
            <div class="searchable-select__input-wrap" @click="openAuthorDropdown">
              <span class="searchable-select__value" :class="{ 'is-all': !selectedAuthor }">
                {{ selectedAuthorLabel || __('All users') }}
              </span>
              <span class="searchable-select__arrow" aria-hidden="true"></span>
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
            class="searchable-select searchable-select--people"
            v-click-outside="() => columnistDropdownOpen = false"
          >
            <div class="searchable-select__input-wrap" @click="openColumnistDropdown">
              <span class="searchable-select__value" :class="{ 'is-all': !selectedColumnist }">
                {{ selectedColumnistLabel || __('All columnists') }}
              </span>
              <span class="searchable-select__arrow" aria-hidden="true"></span>
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

          <!-- Тип публикации -->
          <div
            class="searchable-select searchable-select--resource"
            v-click-outside="() => resourceDropdownOpen = false"
          >
            <div class="searchable-select__input-wrap" @click="resourceDropdownOpen = !resourceDropdownOpen">
              <span class="searchable-select__value" :class="{ 'is-all': !selectedResource }">
                {{ selectedResourceLabel || __('All publications') }}
              </span>
              <span class="searchable-select__arrow" aria-hidden="true"></span>
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
        </div>
      </div>
    </div>

    <div class="news-cal-calendar-wrap">
      <div class="news-cal-stats">
        <div class="news-cal-stats__row">
          <span class="news-cal-stats__metric">
            <span class="news-cal-stats__label">{{ __('Publications count') }}</span>
            <span class="news-cal-stats__value">{{ formatStatNumber(totalEvents) }}</span>
          </span>
          <span class="news-cal-stats__sep" aria-hidden="true">·</span>
          <span class="news-cal-stats__metric">
            <span class="news-cal-stats__label">{{ __('Publication views count') }}</span>
            <span class="news-cal-stats__value">{{ formatStatNumber(totalViews) }}</span>
          </span>
        </div>
      </div>
      <div ref="calendar" class="news-calendar-fc-root"></div>
    </div>
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
      totalViews: 0,
      /** YYYY-MM-DD (локальный день) → число публикаций */
      dayPublicationCounts: {},
      /** YYYY-MM-DD → сумма просмотров за день */
      dayViewsTotals: {},
      /** Заголовок текущего месяца из FullCalendar */
      calendarViewTitle: ''
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
    formatStatNumber(value) {
      const n = Number(value);
      if (!Number.isFinite(n)) {
        return '—';
      }
      const lang = document.documentElement.lang || 'en';
      return n.toLocaleString(lang === 'ru' ? 'ru-RU' : 'en-GB');
    },

    /** Для ячейки дня: меньше 1000 — точное число, иначе floor(n/1000) + К/K */
    formatCompactThousands(value) {
      const n = Math.floor(Number(value));
      if (!Number.isFinite(n) || n < 0) {
        return '0';
      }
      if (n < 1000) {
        return String(n);
      }
      const k = Math.floor(n / 1000);
      const lang = document.documentElement.lang || 'en';
      return `${k}${lang === 'ru' ? 'К' : 'K'}`;
    },

    calendarPrev() {
      if (this.calendar) {
        this.calendar.prev();
      }
    },

    calendarNext() {
      if (this.calendar) {
        this.calendar.next();
      }
    },

    calendarToday() {
      if (this.calendar) {
        this.calendar.today();
      }
    },

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

    rebuildDayAggregates() {
      const counts = {};
      const views = {};
      for (const e of this.events) {
        if (!e.start) {
          continue;
        }
        const dt = new Date(e.start);
        const key = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
        counts[key] = (counts[key] || 0) + 1;
        const v = Number(e.views_count);
        views[key] = (views[key] || 0) + (Number.isFinite(v) ? v : 0);
      }
      this.dayPublicationCounts = counts;
      this.dayViewsTotals = views;
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
        this.rebuildDayAggregates();
        this.calendar.removeAllEvents();
        this.calendar.addEventSource(this.events);

        this.totalEvents = response.data.totalEvents;
        this.totalViews = response.data.totalViews;

        this.$nextTick(() => {
          if (this.calendar) {
            this.calendar.render();
          }
        });
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
      headerToolbar: false,
      height: 'auto',
      events: this.events,
      eventOrder: '-start', // Обратная сортировка по дате начала
      locale: calendarLocale, // Динамическая локаль
      locales: calendarLocales,

      // Обновление при смене месяца
      datesSet: (info) => {
        this.currentStart = info.startStr;
        this.currentEnd = info.endStr;
        this.calendarViewTitle = info.view.title;
        this.fetchEvents(info.startStr, info.endStr);
      },

      dayCellContent: (arg) => {
        const y = arg.date.getFullYear();
        const m = String(arg.date.getMonth() + 1).padStart(2, '0');
        const d = String(arg.date.getDate()).padStart(2, '0');
        const key = `${y}-${m}-${d}`;
        const n = this.dayPublicationCounts[key] || 0;
        const v = this.dayViewsTotals[key] || 0;
        const statsClass = n === 0 && v === 0 ? ' news-calendar-day-stats--zero' : '';
        const statsLabel = this.__('Day cell stats label');
        const viewsStr = this.formatCompactThousands(v);
        /* FC сам оборачивает это во внешний <a class="fc-daygrid-day-number"> — внутри нельзя вкладывать второй <a> */
        return {
          html: `<div class="news-calendar-day-top-row">
            <span class="news-calendar-day-num">${arg.dayNumberText}</span>
            <span class="news-calendar-day-stats${statsClass}">
              <span class="news-calendar-day-stat-line">${statsLabel} ${n}</span>
              <span class="news-calendar-day-stat-sep" aria-hidden="true">•</span>
              <span class="news-calendar-day-stat-line">${viewsStr}</span>
            </span>
          </div>`
        };
      },

      eventContent: function(arg) {
        // Контейнер для события
        let eventContainer = document.createElement('div');
        eventContainer.style.display = 'flex';
        eventContainer.style.flexDirection = 'column';
        eventContainer.style.alignItems = 'flex-start';
        eventContainer.style.width = '100%';
        eventContainer.style.padding = '2px 5px';

        // Заголовок события (чёрный: глобальные стили Nova для ссылок иначе дают синий)
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
        eventTitle.style.color = '#171717';

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
    this.calendarViewTitle = this.calendar.view.title;
    this.fetchResources();
    this.fetchEvents();
  }
}
</script>

<style scoped>
.news-calendar-container {
  padding: 0px;
}

.news-calendar-title {
  margin: 0 0 18px;
  font-size: 1.875rem;
  line-height: 1.25;
  font-weight: 700;
  color: #e54839;
}

/* Верхняя плашка: белый фон, скругление, лёгкая рамка */
.news-cal-toolbar {
  margin: 0;
  padding: 20px 24px;
  background: #fff;
  border: 1px solid #e8e8ea;
  border-radius: 8px;
  box-shadow: none;
  box-sizing: border-box;
}

.news-cal-toolbar__inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 14px 24px;
}

.news-cal-toolbar__month {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-start;
  gap: 8px 10px;
  flex: 0 1 auto;
}

/* Предыдущий / следующий месяц + заголовок */
.news-cal-nav__field {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  border-radius: 0;
  overflow: visible;
  box-sizing: border-box;
}

.news-cal-toolbar__filters {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex: 1 1 220px;
  min-width: 0;
}

@media (max-width: 720px) {
  .news-cal-toolbar__inner {
    flex-direction: column;
    align-items: stretch;
  }

  .news-cal-toolbar__month {
    justify-content: center;
    align-items: center;
  }

  .news-cal-toolbar__filters {
    justify-content: flex-start;
  }
}

.news-cal-nav__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: #171717;
  color: #fff;
  font-size: 1.25rem;
  line-height: 1;
  font-weight: 600;
  cursor: pointer;
  box-sizing: border-box;
  flex-shrink: 0;
}

.news-cal-nav__btn:hover {
  background: #333;
  color: #fff;
}

.news-cal-nav__title {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 9.5rem;
  max-width: 17rem;
  padding: 0 16px;
  font-size: 0.9375rem;
  font-weight: 700;
  color: #e54839;
  text-align: center;
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: #fff;
  border: 0;
  box-sizing: border-box;
}

.news-cal-nav__today {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0 0 0 28px;
  padding: 4px 2px;
  min-width: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  text-transform: none;
  letter-spacing: normal;
  color: #171717;
  text-decoration: underline;
  text-decoration-style: dashed;
  text-underline-offset: 3px;
  cursor: pointer;
  box-sizing: border-box;
}

.news-cal-nav__today:hover {
  color: #333;
  text-decoration-style: dashed;
}

.news-cal-nav__today:focus-visible {
  outline: 2px solid #171717;
  outline-offset: 2px;
}

/* Обёртка: серая плашка без рамки + календарь с рамкой */
.news-cal-calendar-wrap {
  margin: 0;
  border: none;
  box-sizing: border-box;
}

/* Строка статистики над сеткой */
.news-cal-stats {
  margin: 0;
  padding: 11px 18px;
  background: transparent;
  box-sizing: border-box;
}

.news-cal-stats__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 4px;
  font-size: 13px;
  line-height: 1.35;
  font-weight: 600;
  color: #171717;
}

.news-cal-stats__metric {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.news-cal-stats__label {
  font-size: inherit;
  font-weight: 600;
  line-height: inherit;
  color: inherit;
  font-variant-numeric: tabular-nums;
}

.news-cal-stats__value {
  font-size: inherit;
  font-weight: 700;
  line-height: inherit;
  color: inherit;
  font-variant-numeric: tabular-nums;
}

.news-cal-stats__sep {
  color: #9ca3af;
  font-weight: 400;
  padding: 0 4px;
  user-select: none;
  font-size: inherit;
}

.news-calendar-fc-root {
  width: 100%;
}

/* Селекторы: плоский вид, тонкие границы (как на референсе) */
.searchable-select {
  position: relative;
  min-width: 200px;
}

.searchable-select__input-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 5px 10px;
  border: 1px solid #d4d4d4;
  border-radius: 2px;
  font-size: 13px;
  background: #fff;
  cursor: pointer;
  user-select: none;
  min-height: 29px;
  box-sizing: border-box;
}

.searchable-select__input-wrap:hover {
  border-color: #bdbdbd;
}

.searchable-select__value {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 700;
  color: #171717;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  font-size: 12px;
}

/* Шеврон вниз (линии 2px — лучше читается, чем символ ▾) */
.searchable-select__arrow {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  margin-left: 6px;
  color: #3f3f3f;
}

.searchable-select__arrow::after {
  content: '';
  display: block;
  border: solid currentColor;
  border-width: 0 2px 2px 0;
  padding: 3px;
  margin-top: -2px;
  transform: rotate(45deg);
  box-sizing: content-box;
}

.searchable-select__input-wrap:hover .searchable-select__arrow {
  color: #171717;
}

.searchable-select__dropdown {
  position: absolute;
  top: calc(100% + 1px);
  left: 0;
  z-index: 1000;
  background: #fff;
  border: 1px solid #d4d4d4;
  border-radius: 2px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  min-width: 100%;
  max-width: 320px;
  box-sizing: border-box;
}

.searchable-select__search {
  display: block;
  width: 100%;
  padding: 8px 12px;
  border: none;
  border-bottom: 1px solid #e8e8e8;
  font-size: 12px;
  outline: none;
  box-sizing: border-box;
  color: #171717;
}

.searchable-select__search::placeholder {
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  font-size: 11px;
}

.searchable-select__options {
  max-height: 220px;
  overflow-y: auto;
}

.searchable-select__option {
  padding: 9px 12px;
  font-size: 13px;
  cursor: pointer;
  white-space: nowrap;
  border-bottom: 1px solid #ececec;
  color: #171717;
  font-weight: 600;
}

.searchable-select__options .searchable-select__option:last-child {
  border-bottom: none;
}

.searchable-select__option:hover {
  background: #f5f5f5;
}

.searchable-select__option.is-selected {
  background: #f0f0f0;
  font-weight: 700;
}

.searchable-select__empty {
  padding: 10px 12px;
  font-size: 12px;
  color: #9ca3af;
  font-style: normal;
  border-bottom: none;
}

.searchable-select__option--all {
  font-weight: 700;
}

.searchable-select__value.is-all {
  font-weight: 700;
}

/* Только тип публикации: пункты списка капсом как на макете */
.searchable-select--resource .searchable-select__option {
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0.02em;
}

/* Авторы / колумнисты: имена в обычном регистре; «все пользователи» — как ALL PUBLICATIONS */
.searchable-select--people .searchable-select__value {
  text-transform: none;
  font-weight: 600;
  font-size: 13px;
  letter-spacing: normal;
}

.searchable-select--people .searchable-select__value.is-all {
  text-transform: uppercase;
  letter-spacing: 0.02em;
  font-size: 12px;
  font-weight: 700;
}

.searchable-select--people .searchable-select__option {
  font-weight: 500;
}

.searchable-select--people .searchable-select__option--all {
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0.02em;
  font-weight: 700;
}
</style>

<style>
/* DOM ячеек создаёт FullCalendar — scoped не попадает на внутренности */
/* Фирменный красный */
.news-calendar-container {
  --news-cal-brand: #e54839;
  --news-cal-brand-hover: #c93a2e;
  --news-cal-brand-active: #a82f24;
  --news-cal-ink: #171717;
}

/* Тулбар FC отключён (headerToolbar: false) — на всякий случай */
.news-calendar-container .fc .fc-header-toolbar {
  display: none !important;
}

/* Общая рамка у .news-cal-calendar-wrap — не дублируем внешний border у FC */
.news-cal-calendar-wrap .news-calendar-fc-root .fc {
  border: none !important;
}

/* Без внутреннего скролла: месяц растягивается по содержимому */
.news-cal-calendar-wrap .fc .fc-scroller-harness,
.news-cal-calendar-wrap .fc .fc-scroller-harness-liquid {
  height: auto !important;
  overflow: visible !important;
}

.news-cal-calendar-wrap .fc .fc-scroller {
  overflow: visible !important;
  height: auto !important;
}

.news-cal-calendar-wrap .fc .fc-view-harness,
.news-cal-calendar-wrap .fc .fc-view-harness-active {
  height: auto !important;
}

/* Кнопки, события: фирменный красный у UI; плашки событий — светлые с чёрным текстом */
.news-calendar-container .fc {
  --fc-button-bg-color: var(--news-cal-brand);
  --fc-button-border-color: var(--news-cal-brand);
  --fc-button-text-color: #fff;
  --fc-button-hover-bg-color: var(--news-cal-brand-hover);
  --fc-button-hover-border-color: var(--news-cal-brand-hover);
  --fc-button-active-bg-color: var(--news-cal-brand-active);
  --fc-button-active-border-color: var(--news-cal-brand-active);
  --fc-event-bg-color: #ececec;
  --fc-event-border-color: #d6d6d6;
  --fc-event-text-color: var(--news-cal-ink);
}

/* Дни недели (Пн, Вт, …) */
.news-calendar-container .fc-col-header-cell,
.news-calendar-container .fc-col-header-cell-cushion {
  color: var(--news-cal-brand) !important;
}

.news-calendar-container .fc-col-header-cell-cushion {
  font-weight: 700;
  padding: 10px 12px;
}

/* FC по умолчанию row-reverse — один дочерний блок уезжает вправо */
.news-calendar-container .fc-daygrid-day-top {
  flex-direction: row;
  align-items: center;
  width: 100%;
}

/*
 * dayCellContent ренерится ВНУТРИ внешнего <a class="fc-daygrid-day-number"> (по умолчанию inline).
 * Без display:block + width:100% внутренняя flex-строка сжимается — «Всего» и число слипаются слева.
 */
.news-calendar-container .fc-daygrid-day-top > a.fc-daygrid-day-number {
  display: block !important;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  text-decoration: none !important;
  color: inherit;
  padding: 0 !important; /* отступы только у строки — как 2px 5px у публикаций */
}

.news-calendar-container .fc-daygrid-day-top .news-calendar-day-top-row {
  flex: 1 1 auto;
  min-width: 0;
  max-width: 100%;
}

.news-calendar-container .news-calendar-day-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  gap: 4px;
  box-sizing: border-box;
  padding: 4px 6px;
  background: #171717;
  color: #fff;
}

/* Номер дня и инфо — белые на чёрной плашке */
.news-calendar-container .news-calendar-day-top-row .news-calendar-day-num {
  flex: 0 0 auto;
  text-align: left;
  color: #fff !important;
  font-weight: 700;
}

.news-calendar-container .fc-daygrid-day-top > a.fc-daygrid-day-number:hover .news-calendar-day-top-row {
  background: #333;
}

.news-calendar-container .news-calendar-day-stats {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 3px 5px;
  font-weight: 600;
  color: #fff !important;
  font-size: 0.72em;
  line-height: 1.25;
  min-width: 0;
  flex: 1 1 auto;
  text-align: right;
}

.news-calendar-container .news-calendar-day-stat-line {
  white-space: nowrap;
}

.news-calendar-container .news-calendar-day-stat-sep {
  font-weight: 400;
  opacity: 0.85;
  user-select: none;
}

.news-calendar-container .news-calendar-day-stats--zero {
  font-weight: 500;
  color: rgba(255, 255, 255, 0.78) !important;
}

/* Заголовки в списке событий — чёрные (обёртка a.fc-event в Nova часто синяя) */
.news-calendar-container a.fc-event,
.news-calendar-container a.fc-event .fc-event-main {
  color: var(--news-cal-ink) !important;
}

.news-calendar-container a.fc-event strong {
  color: inherit !important;
  font-weight: 700;
}
</style>
