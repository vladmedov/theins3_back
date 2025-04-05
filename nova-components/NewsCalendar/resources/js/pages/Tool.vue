<template>
  <div class="news-calendar-container">
    <h1 class="font-normal text-xl md:text-xl">{{ __('News calendar') }}</h1>
    
    <!-- Фильтры -->
    <div class="filter-container">
      <!-- Тип публикации -->
      <select v-model="selectedResource" @change="fetchEvents">
        <option value="">{{ __('All publications') }}</option>
        <option v-for="resource in resources.post_types" :key="resource.value" :value="resource.value">
          {{ resource.label }}
        </option>
      </select>

      <!-- Автор (для всех, кроме opinion) -->
      <select v-model="selectedAuthor" @change="fetchEvents" v-if="selectedResource !== 'opinion'">
        <option value="">{{ __('All users') }}</option>
        <option v-for="author in resources.authors" :key="author.id" :value="author.id">
          {{ author.name }}
        </option>
      </select>

      <!-- Колумнист (только для opinion) -->
      <select v-model="selectedColumnist" @change="fetchEvents" v-if="selectedResource === 'opinion'">
        <option value="">{{ __('All columnists') }}</option>
        <option v-for="columnist in resources.columnists" :key="columnist.id" :value="columnist.id">
          {{ columnist.name }}
        </option>
      </select>
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
  data() {
    return {
      calendar: null,
      events: [],
      selectedResource: '',
      selectedAuthor: '',
      selectedColumnist: '',
      resources: {
        post_types: [],
        authors: [],
        columnists: []
      },
      totalEvents: 0,  // Общее количество новостей
      totalViews: 0    // Сумма просмотров
    };
  },
  methods: {
    /**
     * Получение списка ресурсов для фильтров (типы публикаций, авторы, колумнисты)
     */
    fetchResources() {
      axios.get('/nova-vendor/news-calendar/resources')
        .then(response => {
          this.resources = response.data;
        });
    },

    /**
     * Получение событий и статистики
     */
    fetchEvents(startDate = null, endDate = null) {
      axios.get('/nova-vendor/news-calendar/events', {
        params: {
          resource: this.selectedResource,
          author_id: this.selectedAuthor,
          columnist_id: this.selectedColumnist,
          start: startDate,
          end: endDate
        }
      }).then(response => {
        // Обновление событий в календаре
        this.events = response.data.events;
        this.calendar.removeAllEvents();
        this.calendar.addEventSource(this.events);

        // Обновление статистики
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
  margin-bottom: 20px; /* Отступ до календаря */
  font-size: 14px;
  color: #555;
  background: #f9f9f9;
  padding: 5px 10px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
</style>
