<script setup lang="ts">
import { gotoMainPage, gotoPrevStudent, gotoNextStudent } from '../../../../ts/ta-grading-toolbar';
import NavigationButton from '@/components/ta_grading/NavigationButton.vue';
import PanelSelectorModal from '@/components/ta_grading/PanelSelectorModal.vue';
import { showSettings } from '../../../../ts/ta-grading-keymap';
import { exchangeTwoPanels, taLayoutDet, toggleFullScreenMode, getSavedTaLayoutDetails } from '../../../../ts/ta-grading-panels';

import Cookies from 'js-cookie';

const { homeUrl, prevStudentUrl, nextStudentUrl, progress, clusteringEnabled, clustersExist, taGradingClusterMode } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
    clusteringEnabled?: boolean;
    clustersExist?: boolean;
    taGradingClusterMode?: boolean;
}>();
const toggleClusteringMode = () => {
    if (!clustersExist) {
        return; // Disabled if no clusters
    }
    const currentMode = taGradingClusterMode;
    Cookies.set('ta_grading_cluster_mode', currentMode ? 'false' : 'true', { expires: 1, path: '/' });
    window.location.reload();
};
const emit = defineEmits<{
    'select-layout': [layout: { panels: number; isLeftTaller: boolean; twoInRight: boolean }];
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
    v-if="clusteringEnabled && clustersExist"
    :on-click="toggleClusteringMode"
    :visible-icon="taGradingClusterMode ? 'fa-chart-diagram' : 'fa-timeline'"
    button-id="toggle-cluster-mode"
    :title="taGradingClusterMode ? 'Clustering Mode: ON (Click to disable)' : 'Clustering Mode: OFF (Click to enable)'"
    :style="taGradingClusterMode ? 'color: var(--standard-vibrant-yellow);' : ''"
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
