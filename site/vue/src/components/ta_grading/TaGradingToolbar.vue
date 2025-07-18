<script setup lang="ts">
import { defineProps } from 'vue';
import { gotoMainPage, gotoPrevStudent, gotoNextStudent } from '../../../../ts/ta-grading-toolbar';
import NavigationButton from '@/components/ta_grading/NavigationButton.vue';
import { togglePanelSelectorModal } from '../../../../ts/panel-selector-modal';
import { showSettings } from '../../../../ts/ta-grading-keymap';
import { exchangeTwoPanels, taLayoutDet, toggleFullScreenMode, getSavedTaLayoutDetails } from '../../../../ts/ta-grading-panels';

const { homeUrl, prevStudentUrl, nextStudentUrl, progress } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
}>();

// need to assign because ta-grading-panels-init.ts is not called
Object.assign(taLayoutDet, getSavedTaLayoutDetails());
if (taLayoutDet.isFullScreenMode) {
    toggleFullScreenMode();
}
const fullScreened = taLayoutDet.isFullScreenMode;
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

  <NavigationButton
    :on-click="showSettings"
    visible-icon="fa-wrench"
    button-id="grading-setting-btn"
    title="Show Grading Settings"
    optional-spanid="grading-setting-btn"
    optional-test-id="grading-setting-btn"
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
