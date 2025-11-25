<script setup lang="ts">
import { inject, onMounted, ref } from 'vue';
import Popup from '@/components/Popup.vue';
import TaGradingGeneralSettings from '@/components/ta_grading/TaGradingGeneralSettings.vue';
import { getDefaultSettingsData, loadTAGradingSettingData, optionsCallback, type SettingsData } from '@/ts/ta-grading-general-settings';
import TaGradingHotkeySettings from './TaGradingHotkeySettings.vue';
import { remapGetLS, type KeymapEntry } from '@/ts/ta-grading-keymap';

const { fullAccess, isVisible } = defineProps<{
    fullAccess: boolean;
    isVisible: boolean;
}>();

const emit = defineEmits<{
    changeNavigationTitles: [titles: [string, string]];
    changeSettingsVisibility: [visible: boolean];
}>();

const keymap = inject<KeymapEntry<unknown>[]>('keymap', []);

const togglePopup = () => {
    console.log('Toggling settings popup visibility');
    // when the popup becomes available, we need to load the keymap from local storage
    if (!isVisible) {
        console.log('Loading keymap from local storage');
        keymap.forEach((hotkey) => {
            const storedCode = remapGetLS(hotkey.name);
            if (storedCode) {
                hotkey.code = storedCode;
            }
            if (!hotkey.originalCode) {
                hotkey.originalCode = hotkey.code || 'Unassigned';
            }
        });
    }
    emit('changeSettingsVisibility', !isVisible);
};

function handleChangeNavigationTitles(titles: [string, string]) {
    emit('changeNavigationTitles', titles);
}

const settingsData = ref<SettingsData>(getDefaultSettingsData(fullAccess));

onMounted(() => {
    loadTAGradingSettingData(settingsData);

    // Load initial settings values
    for (const setting of settingsData.value) {
        for (const option of setting.values) {
            optionsCallback(option, emit);
        }
    }
});
</script>

<template>
  <Popup
    id="settings-popup"
    title="Settings"
    :visible="isVisible"
    dismiss-text="Close"
    @toggle="togglePopup"
  >
    <template #trigger>
      <slot
        name="trigger"
        :toggle-popup="togglePopup"
      >
        <button
          class="btn btn-primary"
          @click="togglePopup"
        >
          You should probably override this
        </button>
      </slot>
    </template>

    <template #default>
      <TaGradingGeneralSettings
        :settings-data="settingsData"
        @change-navigation-titles="handleChangeNavigationTitles"
      />

      <TaGradingHotkeySettings />
    </template>
  </Popup>
</template>
