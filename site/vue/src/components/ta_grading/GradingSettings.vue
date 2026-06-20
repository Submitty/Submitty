<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import {
    settingsData,
    loadTAGradingSettingData,
    applySettingChange,
    getKeymap,
    updateKeymapAndStorage,
    isKeyAlreadyBound,
    eventToKeyCode,
} from '../../../../ts/ta-grading-keymap';

const props = defineProps<{
    fullAccess: boolean;
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
    settings.value = JSON.parse(JSON.stringify(settingsData));
}

function refreshHotkeys() {
    const km = getKeymap();
    hotkeys.value = km.map((h) => ({
        name: h.name,
        code: h.code || h.originalCode || 'Unassigned',
        originalCode: h.originalCode,
    }));
}

function show() {
    refreshSettings();
    refreshHotkeys();
    visible.value = true;
    window.__settingsPopupVisible = true;
}

function close() {
    visible.value = false;
    remapActive.value = false;
    remapIndex.value = -1;
    window.__settingsPopupVisible = false;
}

function onDocumentKeydown(e: KeyboardEvent) {
    if (!visible.value) return;

    if (e.key === 'Escape') {
        close();
        return;
    }

    if (remapActive.value) {
        e.preventDefault();
    }
}

function onDocumentKeyup(e: KeyboardEvent) {
    if (!visible.value || !remapActive.value) return;

    const code = eventToKeyCode(e);
    e.preventDefault();

    if (isKeyAlreadyBound(remapIndex.value, code)) {
        return;
    }

    updateKeymapAndStorage(remapIndex.value, code);
    remapActive.value = false;
    remapIndex.value = -1;
    refreshHotkeys();
}

function startRemap(index: number) {
    if (remapActive.value) return;
    remapActive.value = true;
    remapIndex.value = index;
}

function unsetRemap(index: number) {
    updateKeymapAndStorage(index, 'Unassigned');
    refreshHotkeys();
}

function restoreAll() {
    const km = getKeymap();
    km.forEach((_, index) => {
        updateKeymapAndStorage(index, km[index].originalCode!);
    });
    refreshHotkeys();
}

function removeAll() {
    const km = getKeymap();
    km.forEach((_, index) => {
        updateKeymapAndStorage(index, 'Unassigned');
    });
    refreshHotkeys();
}

function onSettingChange(storageCode: string, value: string) {
    applySettingChange(storageCode, value);
}

function onTogglePopup(e: Event) {
    const detail = (e as CustomEvent).detail as { show?: boolean } | undefined;
    if (detail?.show) {
        show();
    }
    else {
        close();
    }
}

onMounted(() => {
    document.addEventListener('toggle-settings-popup', onTogglePopup);
    document.addEventListener('keydown', onDocumentKeydown);
    document.addEventListener('keyup', onDocumentKeyup);
});

onUnmounted(() => {
    document.removeEventListener('toggle-settings-popup', onTogglePopup);
    document.removeEventListener('keydown', onDocumentKeydown);
    document.removeEventListener('keyup', onDocumentKeyup);
});
</script>

<template>
  <div v-if="visible" class="popup-form" data-testid="settings-popup">
    <div class="popup-box" data-testid="popup-overlay" @click.self="close">
      <div class="popup-window" data-testid="popup-window" @click.stop>
        <div class="form-title">
          <h1>Settings</h1>
          <button
            class="btn btn-default close-button"
            data-testid="close-button"
            tabindex="-1"
            @click="close"
          >
            Close
          </button>
        </div>

        <div id="settings-content" class="form-body">
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
                  <tr v-for="setting in group.values" :key="setting.storageCode">
                  <td v-if="setting.options && Object.keys(setting.options).length > 0">{{ setting.name }}</td>
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
                        :selected="optValue === setting.currValue"
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

          <table class="ta-grading-setting-list" data-testid="hotkeys-list">
            <thead>
              <tr>
                <th>Action</th>
                <th>Hotkey</th>
                <th>Remove</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(hotkey, index) in hotkeys" :key="index">
              <td>{{ hotkey.name || 'Unassigned' }}</td>
              <td>
                <button
                  :data-testid="'remap-' + index"
                  :class="[
                    'btn',
                    'remap-button',
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

          <br>
          <div class="form-buttons">
            <div class="form-button-container">
              <button
                class="btn btn-default close-button key_to_click"
                tabindex="0"
                @click="close"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.popup-form {
    height: 100vh;
}
.popup-window {
    height: calc(100vh - 20px);
}
</style>
