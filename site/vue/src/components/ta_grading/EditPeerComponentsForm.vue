<script setup lang="ts">
import { ref } from 'vue';
import Popup from '../Popup.vue';

interface PeerComponent {
    id: string;
    title: string;
    max: number;
    marks: number[];
}

interface MarkInfo {
    title: string;
    points: string;
}

interface PeerDetails {
    graders: Record<string, string[]>;
    marks_assigned: Record<string, Record<string, number[]>>;
}

const props = defineProps<{
    peers: string[];
    submitterId: string;
    gradeableId: string;
    csrfToken: string;
    components: PeerComponent[];
    componentScores: Record<string, Record<string, number>>;
    peerDetails: PeerDetails;
    marks: Record<string, MarkInfo>;
    visible?: boolean;
}>();

const emit = defineEmits<{
    'clear-marks': [detail: {
        submitterId: string;
        gradeableId: string;
        peer: string;
        csrfToken: string;
    }];
}>();

const visible = ref(props.visible);
const selectedPeer = ref(props.peers[0] ?? '');

function clearMarks() {
    emit('clear-marks', {
        submitterId: props.submitterId ?? '',
        gradeableId: props.gradeableId ?? '',
        peer: selectedPeer.value,
        csrfToken: props.csrfToken ?? '',
    });
}

function badgeClass(earned: number, max: number): string {
    if (earned < 0) {
        return earned < 0.5 * max ? 'red-background' : 'yellow-background';
    }
    if (earned >= max) {
        return 'green-background';
    }
    if (earned > max * 0.5) {
        return 'yellow-background';
    }
    return 'red-background';
}

function shouldShowBadge(earned: number, max: number): boolean {
    return max > 0 || earned < 0;
}

function badgeText(earned: number, max: number): string {
    if (earned < 0) {
        return `\u2212${Math.abs(earned)} / ${max}`;
    }
    return `${earned} / ${max}`;
}

function isMarkAssigned(componentId: string, peer: string, markId: number): boolean {
    return props.peerDetails?.marks_assigned?.[componentId]?.[peer]?.includes(markId) ?? false;
}

function scoreForComponent(componentId: string, peer: string): number | undefined {
    return props.componentScores?.[componentId]?.[peer];
}

function hasScore(componentId: string, peer: string): boolean {
    return scoreForComponent(componentId, peer) !== undefined;
}
</script>

<template>
  <Popup
    title="Edit Peer Components Form"
    :visible="visible"
    @toggle="visible = !visible"
  >
    <template #trigger />
    <span data-testid="warning-text">
      Select the student whose marks you want to clear
      <br>WARNING this will remove all of the peer grading done by this student:
      <br>
      ** WIP: Editing and deletion of individual components**
    </span>
    <select
      id="edit-peer-select"
      v-model="selectedPeer"
      data-testid="edit-peer-select"
    >
      <option
        v-for="peer in peers"
        :key="peer"
        :value="peer"
      >
        {{ peer }}
      </option>
    </select>
    <div
      v-for="peer in peers"
      v-show="selectedPeer === peer"
      :key="peer"
      class="edit-peer-components-block"
    >
      <button
        type="button"
        class="btn"
        data-testid="clear-peer-marks"
        @click="clearMarks"
      >
        Clear All Grading
      </button>
      <br>
      <div
        v-for="component in components"
        :key="component.id"
        :data-testid="'component-block-' + component.id"
      >
        <div
          v-if="hasScore(component.id, peer)"
          class="box-badge"
          data-testid="box-badge"
        >
          <span
            v-if="shouldShowBadge(scoreForComponent(component.id, peer) ?? 0, component.max)"
            class="badge"
            :class="badgeClass(scoreForComponent(component.id, peer) ?? 0, component.max)"
            data-testid="score-pill-badge"
          >
            {{ badgeText(scoreForComponent(component.id, peer) ?? 0, component.max) }}
          </span>
          <div
            v-else
            class="no-badge"
            data-testid="no-badge"
          />
        </div>
        <span
          class="component-title col-no-gutters"
          data-testid="component-title"
        >
          <b>{{ component.title }}</b>
        </span>
        <div
          class="received-marks-list container"
          data-testid="marks-list"
        >
          <div
            v-for="markId in component.marks"
            :key="markId"
            class="row"
            :data-testid="'mark-row-' + markId"
          >
            <div class="col-no-gutters indicator">
              <i
                v-if="isMarkAssigned(component.id, peer, markId)"
                class="far fa-check-square fa-1g"
                data-testid="mark-checked"
              />
              <i
                v-else
                class="far fa-square fa-1g"
                data-testid="mark-unchecked"
              />
            </div>
            <div class="col-no-gutters point-value">
              <span data-testid="mark-points">{{ marks[String(markId)]?.points }}</span>
            </div>
            <div class="col">
              <span
                style="white-space: pre-wrap;"
                data-testid="mark-title"
              >{{ marks[String(markId)]?.title }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Popup>
</template>
