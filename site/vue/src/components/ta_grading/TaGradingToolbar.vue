<script setup lang="ts">
import { defineProps } from 'vue';
import { gotoMainPage, gotoPrevStudent, gotoNextStudent } from '../../../../ts/ta-grading-toolbar';
import NavigationButton from '@/components/ta_grading/NavigationButton.vue';
import { toggleFullScreenMode, exchangeTwoPanels } from '../../../../ts/ta-grading-panels';
import { togglePanelSelectorModal } from '../../../../ts/panel-selector-modal';
import { showSettings } from '../../../../ts/ta-grading-keymap';

const { homeUrl, prevStudentUrl, nextStudentUrl, progress } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
}>();
</script>

<template>
  <NavigationButton
    :on-click="gotoMainPage"
    :icons="['fa-home']"
    button-id="main-page"
    title="Go to the main page"
    :optional-href="homeUrl"
  />

  <NavigationButton
    :on-click="gotoPrevStudent"
    :icons="['fa-caret-left']"
    button-id="prev-student"
    title="Previous Student"
    :optional-href="prevStudentUrl"
    optional-test-id="prev-student-navlink"
    optional-spanid="prev-student-navlink"
  />

  <NavigationButton
    :on-click="gotoNextStudent"
    :icons="['fa-caret-right']"
    button-id="next-student"
    title="Next Student"
    :optional-href="nextStudentUrl"
    optional-test-id="next-student-navlink"
    optional-spanid="next-student-navlink"
  />

  <NavigationButton
    :on-click="toggleFullScreenMode"
    :icons="['fa-compress', 'fa-expand']"
    button-id="fullscreen-btn"
    title="Toggle the full screen mode"
    optional-spanid="fullscreen-btn-cont"
  />
  <NavigationButton
    :on-click="() => togglePanelSelectorModal(true)"
    :icons="['fa-columns']"
    button-id="two-panel-mode-btn"
    title="Toggle the two panel mode"
    optional-spanid="two-panel-mode-btn"
  />

  <NavigationButton
    :on-click="exchangeTwoPanels"
    :icons="['fa-exchange-alt']"
    button-id="two-panel-exchange-button"
    title="Exchange the panel positions"
    optional-spanid="two-panel-exchange-btn"
  />

  <NavigationButton
    :on-click="showSettings"
    :icons="['fa-wrench']"
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
