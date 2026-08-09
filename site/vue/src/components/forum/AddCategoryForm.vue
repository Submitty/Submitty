<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import flatpickr from 'flatpickr';

const emit = defineEmits<{
    'add-category': [payload: { name: string; visibleDate: string }];
    'max-length': [];
}>();

const MAX_NAME_LENGTH = 50;

const name = ref('');
const visibleDate = ref('');
const dateInput = ref<HTMLInputElement | null>(null);

let flatpickrInstance: flatpickr.Instance | null = null;

function onAddCategory() {
    emit('add-category', {
        name: name.value,
        visibleDate: visibleDate.value,
    });
}

function onNameKeyup() {
    if (name.value.length >= MAX_NAME_LENGTH) {
        emit('max-length');
    }
}

onMounted(() => {
    if (dateInput.value) {
        flatpickrInstance = flatpickr(dateInput.value, {
            allowInput: true,
            dateFormat: 'Y-m-d',
            onChange: (selectedDates, dateStr) => {
                visibleDate.value = dateStr;
            },
            onReady: (selectedDates, dateStr, instance) => {
                const monthNav = instance.calendarContainer.firstChild?.childNodes[1]?.firstChild?.firstChild as Element | null;
                monthNav?.setAttribute('aria-label', 'Month');
            },
        });
    }
});

onBeforeUnmount(() => {
    flatpickrInstance?.destroy();
    flatpickrInstance = null;
});
</script>

<template>
  <span
    class="add-category-form"
    data-testid="add-category-form"
  >
    <input
      v-model="name"
      class="add-category-name-input"
      data-testid="add-category-name-input"
      aria-label="New Category"
      placeholder="New Category"
      type="text"
      name="new_category"
      :maxlength="MAX_NAME_LENGTH"
      @keyup="onNameKeyup"
    >
    <input
      ref="dateInput"
      data-testid="add-category-date-input"
      aria-label="Visible Date"
      placeholder="YYYY-MM-DD"
      class="date_picker flatpickr-input active"
      name="visible_date"
      type="text"
    >
    <button
      type="button"
      class="btn btn-primary btn-sm add-category-button"
      data-testid="add-category-button"
      title="Add new category"
      tabindex="0"
      @click="onAddCategory"
    >
      <i class="fas fa-plus-circle fa-1x" /> Add category
    </button>
  </span>
</template>

<style scoped>
.add-category-form {
    float: right;
}

.add-category-name-input {
    resize: none;
}

.add-category-button {
    margin-left: 10px;
}
</style>
