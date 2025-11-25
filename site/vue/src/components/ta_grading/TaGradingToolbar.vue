<script setup lang="ts">
import NavigationButton from '@/components/ta_grading/NavigationButton.vue';
import TaGradingSettings from '@/components/ta_grading/TaGradingSettings.vue';
import { defineProps, reactive, ref, provide, onMounted } from 'vue';
import { gotoMainPage, gotoPrevStudent, gotoNextStudent } from '../../../../ts/ta-grading-toolbar';
import { togglePanelSelectorModal } from '../../../../ts/panel-selector-modal';
import { exchangeTwoPanels, taLayoutDet, toggleFullScreenMode, getSavedTaLayoutDetails } from '../../../../ts/ta-grading-panels';
import { handleKeyDown, handleKeyUp, initTaGradingHotkeys, type KeymapEntry } from '@/ts/ta-grading-keymap';

const { homeUrl, prevStudentUrl, nextStudentUrl, progress, fullAccess } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
    fullAccess: boolean;
}>();

const keymap = reactive<KeymapEntry<unknown>[]>([]);
const remapping = reactive({ active: false, index: 0 });
const settingsVisible = ref(false);
provide('keymap', keymap);
provide('remapping', remapping);

// need to assign because ta-grading-panels-init.ts is not called
Object.assign(taLayoutDet, getSavedTaLayoutDetails());
if (taLayoutDet.isFullScreenMode) {
    toggleFullScreenMode();
}
const fullScreened = taLayoutDet.isFullScreenMode;

function changeSettingsVisibility(visible: boolean) {
    settingsVisible.value = visible;
}

function changeNavigationTitles([prevTitle, nextTitle]: [string, string]) {
    navigationTitles.value.prevStudentTitle = prevTitle;
    navigationTitles.value.nextStudentTitle = nextTitle;
}

const navigationTitles = ref({
    prevStudentTitle: 'Previous student',
    nextStudentTitle: 'Next student',
});

onMounted(() => {
    initTaGradingHotkeys(keymap);
    window.onkeyup = (e) => handleKeyUp(e, keymap, remapping);
    window.onkeydown = (e) => handleKeyDown(e, keymap, remapping, settingsVisible.value);
});
</script>

<template>
  <NavigationButton
    :on-click="gotoMainPage"
    visible-icon="fa-home"
    button-id="main-page"
    title="Go to the main page"
    :optional-href="homeUrl"
  />

  <NavigationButton
    :on-click="gotoPrevStudent"
    visible-icon="fa-caret-left"
    button-id="prev-student"
    :title="navigationTitles.prevStudentTitle"
    :optional-href="prevStudentUrl"
    optional-test-id="prev-student-navlink"
    optional-spanid="prev-student-navlink"
  />

  <NavigationButton
    :on-click="gotoNextStudent"
    visible-icon="fa-caret-right"
    button-id="next-student"
    :title="navigationTitles.nextStudentTitle"
    :optional-href="nextStudentUrl"
    optional-test-id="next-student-navlink"
    optional-spanid="next-student-navlink"
  />

  <NavigationButton
    :on-click="toggleFullScreenMode"
    visible-icon="fa-expand"
    hidden-icon="fa-compress"
    :display-hidden="fullScreened"
    button-id="fullscreen-btn"
    title="Toggle the full screen mode"
    optional-spanid="fullscreen-btn-cont"
  />
  <NavigationButton
    :on-click="() => togglePanelSelectorModal(true)"
    visible-icon="fa-columns"
    button-id="two-panel-mode-btn"
    title="Toggle the two panel mode"
    optional-spanid="two-panel-mode-btn"
  />

  <NavigationButton
    :on-click="exchangeTwoPanels"
    visible-icon="fa-exchange-alt"
    button-id="two-panel-exchange-button"
    title="Exchange the panel positions"
    optional-spanid="two-panel-exchange-btn"
  />
  <TaGradingSettings
    :full-access="fullAccess"
    :is-visible="settingsVisible"
    @change-navigation-titles="changeNavigationTitles"
    @change-settings-visibility="changeSettingsVisibility"
  >
    <template #trigger="{ togglePopup }">
      <NavigationButton
        :on-click="togglePopup"
        visible-icon="fa-wrench"
        button-id="grading-setting-btn"
        title="Show Grading Settings"
        optional-spanid="grading-setting-btn"
        optional-test-id="grading-setting-btn"
      />
    </template>
  </TaGradingSettings>
  <span
    id="progress-bar-cont"
    class="ta-navlink-cont"
  >
    <progress
      class="progressbar"
      max="100"
      :value="progress"
    />
    <span class="progress-value">
      <b>{{ progress }}%</b>
    </span>
  </span>
</template>
