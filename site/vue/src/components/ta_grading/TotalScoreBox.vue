<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    autoGradingEarned?: number;
    autoGradingTotal?: number;
    taGradingEarned?: number;
    taGradingTotal?: number;
    peerGradeEarned?: number;
    peerTotal?: number;
    combinedPeerScore?: number;
    userGroup: number;
    peerOnlyGrader: boolean;
    peerGradeable: boolean;
    decimalPrecision: number;
}>();

function badgeStyle(earned: number | undefined, total: number | undefined): string {
    if (earned === undefined || total === undefined) {
        return '';
    }
    if (total === 0) {
        if (earned === 0) {
            return ''; // extra credit with nothing earned → no class
        }
        return 'green-background'; // earned points on zero-total → green
    }
    const percent = earned / total;
    if (percent < 0.5) {
        return 'red-background';
    }
    if (percent < 1.0) {
        return 'yellow-background';
    }
    return 'green-background';
}

function fmt(val: number | undefined): string {
    if (val === undefined) {
        return '\u2212';
    }
    return val.toFixed(props.decimalPrecision);
}

const showNonPeer = computed(() => props.userGroup < 4 && !props.peerOnlyGrader);

const showAutoGrading = computed(() => props.autoGradingTotal !== undefined);

const showTaGrading = computed(() => props.taGradingTotal !== undefined && props.taGradingTotal > 0);

const showPeerGrading = computed(() => props.peerTotal !== undefined && props.peerTotal > 0);

const totalEarned = computed(() => {
    let total = 0;
    if (props.peerGradeEarned !== undefined && props.combinedPeerScore !== undefined) {
        total += props.combinedPeerScore;
    }
    if (props.taGradingEarned !== undefined) {
        total += props.taGradingEarned;
    }
    if (props.autoGradingEarned !== undefined) {
        total += props.autoGradingEarned;
    }
    return total;
});

const maxTotal = computed(() => {
    const peer = props.peerTotal ?? 0;
    const ta = props.taGradingTotal ?? 0;
    const auto = props.autoGradingTotal ?? 0;
    return peer + ta + auto;
});

const showTotal = computed(() => showTaGrading.value || showPeerGrading.value || props.autoGradingEarned !== undefined);

const taLabel = computed(() => props.peerGradeable ? 'Non Peer Manual Grading Total' : 'Manual Grading Total');
</script>

<template>
  <template v-if="showNonPeer">
    <div
      v-if="showAutoGrading"
      class="box-title badge-container"
      data-testid="autograding-row"
    >
      <strong
        id="autograding_total"
        class="badge"
        :class="badgeStyle(autoGradingEarned, autoGradingTotal)"
      >
        {{ fmt(autoGradingEarned) }} / {{ fmt(autoGradingTotal) }}
      </strong>
      <strong>Autograding Total</strong>
    </div>
    <div
      v-if="showTaGrading"
      class="box-title badge-container"
      data-testid="manual-grading-row"
    >
      <strong
        id="grading_total"
        class="badge"
        :class="badgeStyle(taGradingEarned, taGradingTotal)"
        data-testid="grading-total"
      >
        {{ fmt(taGradingEarned) }} / {{ fmt(taGradingTotal) }}
      </strong>
      <strong>{{ taLabel }}</strong>
    </div>
    <div
      v-if="showPeerGrading"
      class="box-title badge-container"
      data-testid="individual-peer-row"
    >
      <strong
        id="score_total"
        class="badge"
        :class="badgeStyle(peerGradeEarned, peerTotal)"
      >
        {{ fmt(peerGradeEarned) }} / {{ fmt(peerTotal) }}
      </strong>
      <strong>Individual Peer Grading Total</strong>
    </div>
    <div
      v-if="showPeerGrading"
      class="box-title badge-container"
      data-testid="combined-peer-row"
    >
      <strong
        id="score_total"
        class="badge"
        :class="badgeStyle(combinedPeerScore, peerTotal)"
      >
        {{ fmt(combinedPeerScore) }} / {{ fmt(peerTotal) }}
      </strong>
      <strong>Combined Peer Grading Total</strong>
    </div>
    <div
      v-if="showTotal"
      class="box-title badge-container"
      data-testid="total-row"
    >
      <strong
        id="score_total"
        class="badge"
        :class="badgeStyle(totalEarned, maxTotal)"
      >
        {{ fmt(totalEarned) }} / {{ fmt(maxTotal) }}
      </strong>
      <strong>Total</strong>
    </div>
  </template>

  <div
    v-if="userGroup === 4 || peerOnlyGrader"
    class="box-title badge-container"
    data-testid="my-peer-row"
  >
    <strong
      id="score_total"
      class="badge"
      :class="badgeStyle(peerGradeEarned, peerTotal)"
    >
      {{ fmt(peerGradeEarned) }} / {{ fmt(peerTotal) }}
    </strong>
    <strong>My Peer Grading Total</strong>
  </div>
</template>
