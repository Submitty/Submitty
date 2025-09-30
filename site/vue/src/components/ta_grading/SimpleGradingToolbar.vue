<script setup lang="ts">
import TaGradingSettings from '@/components/ta_grading/TaGradingSettings.vue';
import { defineProps, reactive, ref, provide, onMounted } from 'vue';
import { handleKeyDown, handleKeyUp, initSimpleGradingHotkeys, type KeymapEntry } from '@/ts/ta-grading-keymap';

const { fullAccess, type } = defineProps<{
    fullAccess: boolean;
    type: 'lab' | 'numeric';
}>();

const keymap = reactive<KeymapEntry<unknown>[]>([]);
const remapping = reactive({ active: false, index: 0 });
const settingsVisible = ref(false);
provide('keymap', keymap);
provide('remapping', remapping);

function changeSettingsVisibility(visible: boolean) {
    settingsVisible.value = visible;
}

onMounted(() => {
    initSimpleGradingHotkeys(keymap, type);
    window.onkeyup = (e) => handleKeyUp(e, keymap, remapping);
    window.onkeydown = (e) => handleKeyDown(e, keymap, remapping, settingsVisible.value);
});
</script>

<template>
  <TaGradingSettings
    :full-access="fullAccess"
    :is-visible="settingsVisible"
    @change-settings-visibility="changeSettingsVisibility"
  >
    <template #trigger="{ togglePopup }">
      <button
        id="settings-btn"
        class="btn btn-primary"
        @click="togglePopup"
      >
        Settings
      </button>
    </template>
  </TaGradingSettings>
</template>
