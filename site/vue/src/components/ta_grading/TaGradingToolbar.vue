<script setup lang="ts">
import { gotoMainPage, gotoPrevStudent, gotoNextStudent } from '../../../../ts/ta-grading-toolbar';
import NavigationButton from '@/components/ta_grading/NavigationButton.vue';
import PanelSelectorModal from '@/components/ta_grading/PanelSelectorModal.vue';
import { showSettings } from '../../../../ts/ta-grading-keymap';
import { exchangeTwoPanels, taLayoutDet, toggleFullScreenMode, getSavedTaLayoutDetails } from '../../../../ts/ta-grading-panels';

const { homeUrl, prevStudentUrl, nextStudentUrl, progress, clusteringEnabled, clustersExist, taGradingClusterMode } = defineProps<{
    homeUrl: string;
    prevStudentUrl: string;
    nextStudentUrl: string;
    progress: number;
    clusteringEnabled?: boolean;
    clustersExist?: boolean;
    taGradingClusterMode?: boolean;
}>();

const emit = defineEmits<{
    'select-layout': [layout: { panels: number; isLeftTaller: boolean; twoInRight: boolean }];
    'toggle-cluster-mode': [];
}>();
const toggleClusteringMode = () => {
    if (!clustersExist) {
        return; // Disabled if no clusters
    }
    emit('toggle-cluster-mode');
};

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
    :on-click="gotoPrevStudent"
    visible-icon="fa-caret-left"
    button-id="prev-student"
    title="Previous Student"
    :optional-href="prevStudentUrl"
    optional-spanid="prev-student-navlink"
    optional-testid="prev-student-navlink"
  />

  <NavigationButton
    :on-click="gotoNextStudent"
    visible-icon="fa-caret-right"
    button-id="next-student"
    title="Next Student"
    :optional-href="nextStudentUrl"
    optional-spanid="next-student-navlink"
    optional-testid="next-student-navlink"
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
  />
  <span
    v-if="clusteringEnabled && clustersExist"
    id="toggle-cluster-mode-cont"
    class="ta-navlink-cont"
  >
    <button
      id="toggle-cluster-mode"
      data-testid="toggle-cluster-mode"
      class="invisible-btn cluster-mode-btn"
      :title="taGradingClusterMode ? 'Cluster Grading: ON (Click to disable)' : 'Cluster Grading: OFF (Click to enable)'"
      @click="toggleClusteringMode"
    >
      <i
        class="fas icon-header icon-streched"
        :class="taGradingClusterMode ? 'fa-chart-diagram' : 'fa-grip'"
      />
      <span class="cluster-mode-text">
        {{ taGradingClusterMode ? 'Cluster Grading ON' : 'Cluster Grading OFF' }}
      </span>
    </button>
  </span>
  <span
    id="progress-bar-cont"
    class="ta-navlink-cont"
    data-testid="progress-bar"
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

<style scoped>
.cluster-mode-btn {
    display: flex;
    align-items: center;
}
.cluster-mode-text {
    margin-left: 5px;
    padding-right: 5px;
    font-size: 16px;
    color: var(--text-black);
}
</style>
