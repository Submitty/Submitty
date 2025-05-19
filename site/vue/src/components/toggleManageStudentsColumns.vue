<script setup lang="ts">
import Cookies from 'js-cookie';
import { onMounted, ref } from 'vue';
import Popup from './popup.vue';

const columnIds = [
    'toggle-registration-section',
    'toggle-user-id',
    'toggle-first-name',
    'toggle-last-name',
    'toggle-pronouns',
    'toggle-rotating-section',
    'toggle-time-zone',
    'toggle-view-grades',
    'toggle-late-days',
    'toggle-registration-type',
    'toggle-edit-student',
    'toggle-delete-student',
    'toggle-user-numeric-id',
    'toggle-legal-first-name',
    'toggle-legal-last-name',
    'toggle-email',
    'toggle-secondary-email',
];
const labels = [
    'Registration Section',
    'User ID',
    'Given Name',
    'Family Name',
    'Pronouns',
    'Rotating Section',
    'UTC Offset / Time Zone',
    'View Grades',
    'Late Days',
    'Registration Type',
    'Edit Student',
    'Delete Student',
    'User Numeric ID',
    'Legal Given Name',
    'Legal Family Name',
    'Email',
    'Secondary Email',
];

const selected = ref<boolean[]>([]);

function loadColumns() {
    const cookie = Cookies.get('active_student_columns')?.split('-') || [];
    selected.value = columnIds.map((_, i) => cookie[i] === '1');
}
function saveColumns() {
    Cookies.set(
        'active_student_columns',
        selected.value.map((v) => (v ? 1 : 0)).join('-'),
        { expires: 365, path: '' },
    );
    window.location.reload();
}
function fillAll(val: boolean) {
    selected.value = selected.value.map(() => val);
}

function toggle() {
    visible.value = !visible.value;
    if (!visible.value) {
        loadColumns();
    }
}

const visible = ref(false);

onMounted(loadColumns);
</script>

<template>
  <Popup
    title="Toggle Columns"
    :visible="visible"
    :savable="true"
    @toggle="toggle"
    @save="saveColumns"
  >
    <template #trigger>
      <div class="btn-wrapper">
        <a
          id="toggle-columns"
          data-testid="toggle-columns"
          class="btn btn-primary"
          @click="toggle"
        >Toggle Columns</a>
      </div>
    </template>
    <template #default>
      <p class="toggle-columns-instructions">
        Select which columns you would like to display.
      </p>
      <div class="toggle-columns-menu">
        <div
          v-for="(id, idx) in columnIds"
          :key="id"
          class="toggle-checkbox-area"
        >
          <input
            :id="id"
            v-model="selected[idx]"
            type="checkbox"
            class="toggle-columns-box"
            :data-testid="id"
          />
          <label :for="id">{{ labels[idx] }}</label>
        </div>
      </div>
      <div class="toggle-all-buttons">
        <a
          class="btn btn-primary"
          @click="fillAll(true)"
        >
          All On
        </a> <a
          class="btn btn-primary"
          @click="fillAll(false)"
        >
          All Off
        </a>
      </div>
    </template>
  </Popup>
</template>
