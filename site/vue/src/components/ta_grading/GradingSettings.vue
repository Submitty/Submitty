<script setup lang="ts">
import { ref } from 'vue';
import Popup from '../Popup.vue';
import {
    settingsData,
    loadTAGradingSettingData,
    getKeymap,
    isKeyAlreadyBound,
    eventToKeyCode,
    setRemappingActive,
    notifySettingsVisibility,
} from '../../../../ts/ta-grading-keymap';

const props = defineProps<{
    fullAccess: boolean;
}>();

const emit = defineEmits<{
    'setting-change': [payload: { storageCode: string; value: string }];
    'hotkey-change': [payload: { index: number; code: string }];
}>();

const visible = ref(false);
const remapActive = ref(false);
const remapIndex = ref(-1);

interface HotkeyItem {
    name: string;
    code: string;
    originalCode?: string;
}

const hotkeys = ref<HotkeyItem[]>([]);
const settings = ref<typeof settingsData>([]);

function refreshSettings() {
    loadTAGradingSettingData(props.fullAccess);
    settings.value = JSON.parse(JSON.stringify(settingsData)) as typeof settingsData;
}

function toggle() {
    if (!visible.value) {
        refreshSettings();
        refreshHotkeys();
    }
    visible.value = !visible.value;
    notifySettingsVisibility(visible.value);
    if (!visible.value) {
        setRemappingActive(false);
        remapActive.value = false;
        remapIndex.value = -1;
    }
}

function refreshHotkeys() {
    const km = getKeymap();
    hotkeys.value = km.map((h) => ({
        name: h.name,
        code: h.code || h.originalCode || 'Unassigned',
        originalCode: h.originalCode,
    }));
}

function handleClose() {
    if (remapActive.value) {
        setRemappingActive(false);
        remapActive.value = false;
        remapIndex.value = -1;
    }
    visible.value = false;
    notifySettingsVisibility(false);
}

function onKeyup(e: KeyboardEvent) {
    if (!remapActive.value) {
        return;
    }

    const code = eventToKeyCode(e);
    e.preventDefault();

    if (isKeyAlreadyBound(remapIndex.value, code)) {
        return;
    }

    emit('hotkey-change', { index: remapIndex.value, code });
    setRemappingActive(false);
    remapActive.value = false;
    remapIndex.value = -1;
    refreshHotkeys();
}

function startRemap(index: number) {
    if (remapActive.value) {
        return;
    }
    remapActive.value = true;
    remapIndex.value = index;
    setRemappingActive(true, index);
}

function unsetRemap(index: number) {
    emit('hotkey-change', { index, code: 'Unassigned' });
    refreshHotkeys();
}

function restoreAll() {
    const km = getKeymap();
    km.forEach((_, index) => {
        emit('hotkey-change', { index, code: km[index].originalCode! });
    });
    refreshHotkeys();
}

function removeAll() {
    const km = getKeymap();
    km.forEach((_, index) => {
        emit('hotkey-change', { index, code: 'Unassigned' });
    });
    refreshHotkeys();
}

function onSettingChange(storageCode: string, value: string) {
    emit('setting-change', { storageCode, value });
}
</script>

<template>
  <Popup
    id="settings-popup"
    title="Settings"
    :visible="visible"
    @toggle="handleClose"
  >
    <template #trigger>
      <span class="ta-navlink-cont">
        <button
          class="invisible-btn"
          data-testid="grading-setting-btn"
          id="settings-btn"
          tabindex="0"
          title="Show Grading Settings"
          @click="toggle"
        >
          <i class="fas fa-wrench icon-header icon-streched" />
        </button>
      </span>
    </template>
    <template #default>
      <div
        id="settings-content"
        class="form-body"
        @keyup="onKeyup"
      >
        <div id="ta-grading-settings-list">
          <div
            v-for="group in settings"
            :key="group.id"
          >
            <h2>{{ group.name }}</h2>
            <br>
            <table class="ta-grading-setting-list">
              <thead>
                <tr>
                  <th>Setting</th>
                  <th>Option</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="setting in group.values"
                  :key="setting.storageCode"
                >
                  <td v-if="setting.options && Object.keys(setting.options).length > 0">
                    {{ setting.name }}
                  </td>
                  <td v-if="setting.options && Object.keys(setting.options).length > 0">
                    <select
                      :data-testid="'ta-grading-setting-option-' + setting.storageCode"
                      class="ta-grading-setting-option"
                      :value="setting.currValue"
                      @change="onSettingChange(setting.storageCode, ($event.target as HTMLSelectElement).value)"
                    >
                      <option
                        v-for="(optValue, optKey) in setting.options"
                        :key="optValue"
                        :value="optValue"
                      >
                        {{ optKey }}
                      </option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="hotkeys-header">
          <div class="hotkeys-title-buttons">
            <h2>Hotkeys</h2>
            <div class="hotkeys-buttons">
              <button
                class="btn btn-primary hotkeys-button"
                data-testid="restore-all-hotkeys"
                @click="restoreAll"
              >
                Restore Default
              </button>
              <button
                class="btn btn-danger hotkeys-button"
                data-testid="remove-all-hotkeys"
                @click="removeAll"
              >
                Remove All
              </button>
            </div>
          </div>
        </div>

        <table
          class="ta-grading-setting-list"
          data-testid="hotkeys-list"
        >
          <thead>
            <tr>
              <th>Action</th>
              <th>Hotkey</th>
              <th>Remove</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(hotkey, index) in hotkeys"
              :key="index"
            >
              <td>{{ hotkey.name || 'Unassigned' }}</td>
              <td>
                <button
                  :data-testid="'remap-' + index"
                  class="btn remap-button"
                  :class="[
                    remapActive && remapIndex === index ? 'btn-success' : 'btn-default',
                  ]"
                  tabindex="0"
                  @click="startRemap(index)"
                >
                  {{ remapActive && remapIndex === index ? 'Enter Key...' : hotkey.code }}
                </button>
              </td>
              <td class="button-cell">
                <button
                  :data-testid="'remap-unset-' + index"
                  class="btn btn-danger"
                  tabindex="0"
                  @click="unsetRemap(index)"
                >
                  &times;
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </Popup>
</template>

<style scoped>
.ta-grading-setting-list {
  width: 100%;
  border-collapse: collapse;
}
.ta-grading-setting-list > thead > tr {
  background: var(--standard-hover-light-gray);
}
.ta-grading-setting-list > thead > tr > th,
.ta-grading-setting-list > tbody > tr > td {
  background: transparent;
}
.ta-grading-setting-list > tbody > tr:nth-child(odd) {
  background: var(--default-white);
}
.ta-grading-setting-list > tbody > tr:nth-child(even) {
  background: var(--standard-hover-light-gray);
}
</style>
