<script setup lang="ts">
import { computed, onMounted, toRefs, ref } from 'vue';

const props = defineProps<{
    gradeableId: string;
    anonId: string;
    isTaGrading: boolean;
    hasActiveVersion: boolean;
    hasSubmission: boolean;
    hasOverriddenGrades: boolean;
    versionConflict: boolean;
    isWithdrawnStudent: boolean;
    showVerifyAll: boolean;
    showClearConflicts: boolean;
    showSilentEdit: boolean;
    isPeerGrader: boolean;
    displayVersion: number;
    graderId: string;
    verifierId: string;
    canVerify: boolean;
    allowCustomMarks: boolean;
}>();
const { showVerifyAll, showClearConflicts, showSilentEdit, isPeerGrader, versionConflict } = toRefs(props);

const editMode = ref(false);
const silentEdit = ref(false);

// Version/status warning computed from props
const warningMessage = computed(() => {
    if (!props.hasActiveVersion && !props.hasOverriddenGrades) {
        if (props.hasSubmission) return { text: 'Cancelled Submission', class: 'red-message' };
        return { text: 'No Submission', class: 'red-message' };
    }
    if (props.versionConflict) return { text: 'Select the correct submission version to grade', class: 'red-message' };
    if (props.isWithdrawnStudent) return { text: 'Withdrawn Student', class: 'yellow-message' };
    if (props.hasOverriddenGrades) return { text: 'Overridden Grades', class: 'yellow-message' };
    return null;
});

// Total score box state
interface TotalScoreData {
    user_group: number;
    ta_grading_earned: number | undefined;
    ta_grading_total: number;
    peer_grade_earned: number | undefined;
    peer_total: number;
    auto_grading_earned?: number;
    auto_grading_total?: number;
    auto_grading_complete: boolean;
    combined_peer_score?: number;
    peer_gradeable?: boolean;
    see_peer_grade?: number;
    precision?: number;
}

const totalScores = ref<TotalScoreData | null>(null);

function getBadgeClass(earned: number | undefined, total: number): string {
    if (earned === undefined) return '';
    const pct = earned / total;
    if (pct < 0.5) return 'red-background';
    if (pct < 1) return 'yellow-background';
    if (total === 0 && earned === 0) return '';
    return 'green-background';
}

function getPointPrecision(): number {
    const el = document.getElementById('point_precision_id');
    if (el) {
        const val = el.getAttribute('value');
        if (val !== null) return parseInt(val, 10);
    }
    return 3;
}

function decimalStr(v: number | undefined): string {
    if (v === undefined) return '\u2013';
    const precision = getPointPrecision();
    return parseFloat(v.toFixed(precision)).toString();
}

function readInitialScores(): TotalScoreData | null {
    const dataEl = document.getElementById('gradeable-scores-id');
    if (!dataEl) return null;

    let taEarned: number | undefined;
    let anyPoints = false;
    let taTotal = 0;
    let peerTotal = 0;
    let peerEarned: number | undefined;
    let anyPeerPoints = false;

    document.querySelectorAll('.graded-component-data').forEach((el) => {
        const pts = el.getAttribute('data-total_score');
        if (pts && pts !== '') {
            taEarned = (taEarned ?? 0) + parseFloat(pts);
            anyPoints = true;
        }
    });

    document.querySelectorAll('.peer-graded-component-data').forEach((el) => {
        const pts = el.getAttribute('data-total_score');
        if (pts && pts !== '') {
            peerEarned = (peerEarned ?? 0) + parseFloat(pts);
            anyPeerPoints = true;
        }
    });

    document.querySelectorAll('#component-list .component').forEach((el) => {
        const maxVal = el.getAttribute('data-max_value');
        const isPeer = el.getAttribute('data-peer') === 'true' || el.getAttribute('data-peer') === '1';
        if (maxVal) {
            if (isPeer) {
                peerTotal += parseFloat(maxVal);
            }
            else {
                taTotal += parseFloat(maxVal);
            }
        }
    });

    const scores: TotalScoreData = {
        user_group: 0,
        ta_grading_earned: anyPoints ? taEarned : undefined,
        ta_grading_total: taTotal,
        peer_grade_earned: anyPeerPoints ? peerEarned : undefined,
        peer_total: peerTotal,
        auto_grading_complete: false,
    };

    const autoEarned = dataEl.getAttribute('data-auto_grading_earned');
    const autoTotal = dataEl.getAttribute('data-auto_grading_total');
    if (autoTotal && autoTotal !== '') {
        scores.auto_grading_earned = parseInt(autoEarned ?? '0');
        scores.auto_grading_total = parseInt(autoTotal);
        scores.auto_grading_complete = true;
    }

    return scores;
}

onMounted(() => {
    // Wait for legacy render to complete, then take over total scores
    const timer = setTimeout(() => {
        const initial = readInitialScores();
        if (initial) {
            totalScores.value = initial;
        }

        // Set up bridge for re-renders from legacy refreshTotalScoreBox
        (window as any).__updateTotalScores = (data: TotalScoreData) => {
            totalScores.value = { ...data };
        };

        // Hide the legacy total score container
        const legacyContainer = document.getElementById('total-score-container');
        if (legacyContainer) {
            legacyContainer.style.display = 'none';
        }
    }, 50);

    // Cleanup on unmount
    return () => {
        clearTimeout(timer);
        delete (window as any).__updateTotalScores;
    };
});

const handleVerifyAll = async () => {
  await (window as any).onVerifyAll?.();
};

const handleClearConflicts = async () => {
  await (window as any).updateAllComponentVersions?.();
};

const handleEditModeToggle = async () => {
  await (window as any).onToggleEditMode?.();
  editMode.value = !editMode.value;
};

const handleSilentEditToggle = async () => {
  (window as any).updateCookies?.();
  silentEdit.value = !silentEdit.value;
};
</script>

<template>
  <div>
    <teleport to="#rubric-warnings">
      <div v-if="warningMessage" :class="warningMessage.class">{{ warningMessage.text }}</div>
      <!-- Legacy bridge: isGradingDisabled() checks $('#version-conflict-indicator').length -->
      <div v-if="versionConflict" id="version-conflict-indicator"></div>
    </teleport>
    <teleport to="#rubric-controls">
      <div id="rubric-vue-controls" class="row row-wrap vertical-center"> 
        <div v-if="showVerifyAll" class="col-no-gutters"> 
          <button id="verify-all" class="btn btn-default key_to_click mx-1" @click="handleVerifyAll">Verify All</button> 
        </div>

        <div v-if="showClearConflicts && !versionConflict" class="col-no-gutters"> 
          <button id="change-graded-version" data-testid="change-graded-version" class="btn btn-default key_to_click mx-1" @click="handleClearConflicts">Clear Version Conflicts</button> 
        </div>

        <div v-if="showSilentEdit" class="col-no-gutters">
          <label>
            <input id="silent-edit-id" type="checkbox" class="key_to_click" :checked="silentEdit" @change="handleSilentEditToggle" />
            Silent Regrade (don't change who graded)
          </label>
        </div>

        <div v-if="!isPeerGrader" class="col-no-gutters">
          <label>
            <input id="edit-mode-enabled" type="checkbox" class="key_to_click" :checked="editMode" @change="handleEditModeToggle" />
            Edit Rubric
          </label>
        </div>
      </div>
    </teleport>
    <teleport to="#vue-total-score-container">
      <div v-if="totalScores" id="vue-total-score-box">
        <div v-if="totalScores.auto_grading_complete" class="box-title badge-container">
          <strong class="badge" :class="getBadgeClass(totalScores.auto_grading_earned, totalScores.auto_grading_total ?? 0)">
            {{ decimalStr(totalScores.auto_grading_earned) }} / {{ decimalStr(totalScores.auto_grading_total) }}
          </strong>
          <strong>Autograding Total</strong>
        </div>
        <div v-if="totalScores.ta_grading_earned !== undefined" class="box-title badge-container">
          <strong class="badge" :class="getBadgeClass(totalScores.ta_grading_earned, totalScores.ta_grading_total)">
            {{ decimalStr(totalScores.ta_grading_earned) }} / {{ decimalStr(totalScores.ta_grading_total) }}
          </strong>
          <strong>Manual Grading Total</strong>
        </div>
        <div v-if="totalScores.peer_grade_earned !== undefined" class="box-title badge-container">
          <strong class="badge" :class="getBadgeClass(totalScores.peer_grade_earned, totalScores.peer_total)">
            {{ decimalStr(totalScores.peer_grade_earned) }} / {{ decimalStr(totalScores.peer_total) }}
          </strong>
          <strong>Individual Peer Grading Total</strong>
        </div>
        <div class="box-title badge-container">
          <strong class="badge" :class="getBadgeClass(
            (totalScores.ta_grading_earned ?? 0) + (totalScores.auto_grading_earned ?? 0) + (totalScores.peer_grade_earned ?? 0),
            totalScores.ta_grading_total + (totalScores.auto_grading_total ?? 0) + totalScores.peer_total
          )">
            {{ decimalStr(
              (totalScores.ta_grading_earned ?? 0) + (totalScores.auto_grading_earned ?? 0) + (totalScores.peer_grade_earned ?? 0)
            ) }} / {{ decimalStr(
              totalScores.ta_grading_total + (totalScores.auto_grading_total ?? 0) + totalScores.peer_total
            ) }}
          </strong>
          <strong>Total</strong>
        </div>
      </div>
    </teleport>
  </div>
</template>