<script setup lang="ts">
import { optionsCallback, type SettingsData, type SettingsValue } from '@/ts/ta-grading-general-settings';

const { settingsData } = defineProps<{
    settingsData: SettingsData;
}>();

const emit = defineEmits<{
    changeNavigationTitles: [titles: [string, string]];
}>();

function handleSettingsChange(option: SettingsValue) {
    const value = option.currValue;
    const storageCode = option.storageCode;
    localStorage.setItem(storageCode, value);

    optionsCallback(option, emit);
}
</script>

<template>
  <div id="ta-grading-settings-list">
    <template
      v-for="setting in settingsData"
      :key="setting.id"
    >
      <h2>{{ setting.name }}</h2>
      <table
        :id="setting.id"
        class="ta-grading-setting-list"
      >
        <tbody>
          <tr>
            <th>Setting</th>
            <th>Option</th>
          </tr>
          <tr
            v-for="option in setting.values.filter(option => Object.keys(option.options).length > 0)"
            :key="option.storageCode"
          >
            <td>{{ option.name }}</td>
            <td>
              <select
                :id="option.storageCode"
                v-model="option.currValue"
                :data-storage-code="option.storageCode"
                class="ta-grading-setting-option"
                data-testid="ta-grading-setting-option"
                @change="handleSettingsChange(option)"
              >
                <option
                  v-for="(value, key) in option.options"
                  :key="value"
                  :value="value"
                >
                  {{ key }}
                </option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>
