<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Popup from '@/components/Popup.vue';
import TaGradingGeneralSettings from '@/components/ta_grading/TaGradingGeneralSettings.vue';
import { generateHotkeysList } from '../../../../ts/ta-grading-keymap';
import { getDefaultSettingsData, loadTAGradingSettingData, optionsCallback, type SettingsData } from '@/ts/ta-grading-general-settings';

const { fullAccess } = defineProps<{
    fullAccess: boolean;
}>();

const emit = defineEmits<{
    changeNavigationTitles: [titles: [string, string]];
}>();

const visible = ref(false);

const togglePopup = () => {
    visible.value = !visible.value;
    generateHotkeysList();
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
    :visible="visible"
    dismiss-text="Close"
    @toggle="togglePopup"
  >
    <template #trigger>
      <span
        id="grading-setting-btn"
        class="ta-navlink-cont"
        data-testid="grading-setting-btn"
      >
        <button
          title="Show Grading Settings"
          class="invisible-btn"
          tabindex="0"
          @click="togglePopup"
        >
          <i class="fas fa-wrench icon-header icon-streched" />
        </button>
      </span>
    </template>

    <template #default>
      <TaGradingGeneralSettings
        :settings-data="settingsData"
        @change-navigation-titles="handleChangeNavigationTitles"
      />

      <div id="hotkeys-list" />
    </template>
  </Popup>
</template>
