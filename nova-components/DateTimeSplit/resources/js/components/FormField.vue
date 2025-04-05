<template>
  <DefaultField
    :field="field"
    :errors="errors"
    :show-help-text="showHelpText"
    :full-width-content="fullWidthContent"
  >
    <template #field>
      <div class="flex flex-col space-y-2">
        <Datepicker
          v-model="dateTimeValue"
          :enable-time-picker="true"
          text-input
          :format="formatDateTimeForDisplay"
          :disabled="currentlyIsReadonly"
          :clearable="!field.required"
          :auto-apply="true"
          locale="ru"
          :enable-seconds="true"
          :day-names="['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']"
          :month-names="['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь']"
          :placeholder="field.placeholder || 'Выберите дату и время'"
          :input-class-name="errorClasses + ' w-full form-control form-input form-input-bordered'"
          :menu-class-name="'dp-custom-menu'"
          @update:model-value="handleChange"
        />
      </div>
    </template>
  </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'
import Datepicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

export default {
  mixins: [FormField, HandlesValidationErrors],

  components: {
    Datepicker
  },

  props: ['resourceName', 'resourceId', 'field'],

  data() {
    return {
      dateTimeValue: null
    }
  },

  created() {
    if (this.field.value) {
      this.value = this.field.value;
      this.dateTimeValue = new Date(this.field.value);
      console.log('Инициализировано значение:', this.dateTimeValue);
    }
  },

  methods: {
    formatDateTimeForStorage(date) {
      if (!date || !(date instanceof Date)) return '';

      const pad = (num) => String(num).padStart(2, '0');

      // Преобразуем локальную дату в UTC
      const year = date.getUTCFullYear();
      const month = pad(date.getUTCMonth() + 1);
      const day = pad(date.getUTCDate());
      const hours = pad(date.getUTCHours());
      const minutes = pad(date.getUTCMinutes());
      const seconds = pad(date.getUTCSeconds());

      const milliseconds = String(date.getUTCMilliseconds()).padStart(3, '0');
      const microseconds = `${milliseconds}000`; 

      return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}.${microseconds}Z`;
    },

    formatDateTimeForDisplay(date) {
      if (!date || !(date instanceof Date)) return '';

      const pad = (num) => String(num).padStart(2, '0');

      const day = pad(date.getDate());
      const month = pad(date.getMonth() + 1);
      const year = date.getFullYear();
      const hours = pad(date.getHours());
      const minutes = pad(date.getMinutes());
      const seconds = pad(date.getSeconds());

      return `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
    },

    handleChange(newValue) {
      if (newValue) {
        this.dateTimeValue = newValue
        this.value = this.formatDateTimeForStorage(newValue)
      } else {
        this.dateTimeValue = null
        this.value = ''
      }

      this.$emit('input', this.value)
    },

    fill(formData) {
      formData.append(this.field.attribute, this.value || '')
    }
  }
}
</script>

<style>
.dp__menu {
  z-index: 1050 !important;
  position: fixed !important;
  transition: opacity 0.15s ease-in-out;
}

.dp-custom-menu {
  @apply bg-white border border-gray-300 rounded-lg shadow-lg;
}

.dp-custom-menu .dp__calendar_header {
  @apply bg-gray-50 text-gray-700 font-medium;
}

.dp-custom-menu .dp__cell_inner {
  @apply hover:bg-gray-50 font-normal;
}

.dp-custom-menu .dp__active_date {
  @apply bg-primary-500 text-white;
}

.dp-custom-menu .dp__today {
  @apply border-primary-500;
}

.dp-custom-menu .dp__time_input {
  @apply bg-white border border-gray-300 rounded focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50;
}

.dp-custom-menu .dp__time_col_reg {
  @apply text-gray-700;
}

.dp-custom-menu .dp__preset_ranges {
  @apply bg-gray-50 border-r border-gray-300;
}
</style>