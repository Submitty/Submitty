<script setup lang="ts">
import { gotoMainPage, gotoPrevStudent, gotoNextStudent } from '../../../../ts/ta-grading-toolbar';
import NavigationButton from '@/components/ta_grading/NavigationButton.vue';
import PanelSelectorModal from '@/components/ta_grading/PanelSelectorModal.vue';
import GradingSettings from '@/components/ta_grading/GradingSettings.vue';
import { exchangeTwoPanels, taLayoutDet, toggleFullScreenMode, getSavedTaLayoutDetails } from '../../../../ts/ta-grading-panels';

const { homeUrl, prevStudentUrl, nextStudentUrl, progress, fullAccess } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
    fullAccess: boolean;
}>();

const emit = defineEmits<{
    'select-layout': [layout: { panels: number; isLeftTaller: boolean; twoInRight: boolean }];
    'setting-change': [payload: { storageCode: string; value: string }];
    'hotkey-change': [payload: { index: number; code: string }];
}>();

// need to assign because ta-grading-panels-init.ts is not called
Object.assign(taLayoutDet, getSavedTaLayoutDetails());
if (taLayoutDet.isFullScreenMode) {
    toggleFullScreenMode();
}
const fullScreened = taLayoutDet.isFullScreenMode;

function selectLayout(layout: { panels: number; isLeftTaller: boolean; twoInRight: boolean }) {
    emit('select-layout', layout);
}

function onSettingChange(payload: { storageCode: string; value: string }) {
    emit('setting-change', payload);
}

function onHotkeyChange(payload: { index: number; code: string }) {
    emit('hotkey-change', payload);
}
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
    title="Previous Student"
    :optional-href="prevStudentUrl"
    optional-test-id="prev-student-navlink"
    optional-spanid="prev-student-navlink"
  />

  <NavigationButton
    :on-click="gotoNextStudent"
    visible-icon="fa-caret-right"
    button-id="next-student"
    title="Next Student"
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
    :on-click="exchangeTwoPanels"
    visible-icon="fa-exchange-alt"
    button-id="two-panel-exchange-button"
    title="Exchange the panel positions"
    optional-spanid="two-panel-exchange-btn"
  />

  <PanelSelectorModal @select-layout="selectLayout" />

  <GradingSettings
    :full-access="fullAccess"
    @setting-change="onSettingChange"
    @hotkey-change="onHotkeyChange"
  />
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
