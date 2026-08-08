<script setup lang="ts">
import { ref } from 'vue';
import Popup from '../Popup.vue';

interface PeerComponent {
    id: string;
    title: string;
    max: number;
    marks: number[];
    extra_credit?: boolean;
}

interface MarkInfo {
    title: string;
    points: string;
}

interface PeerDetails {
    graders: Record<string, string[]>;
    marks_assigned: Record<string, Record<string, number[]>>;
    graded_versions?: Record<string, Record<string, number>>;
    version_conflicts?: Record<string, Record<string, boolean>>;
}

const props = defineProps<{
    peers: string[];
    peerNames?: Record<string, string>;
    submitterId: string;
    gradeableId: string;
    csrfToken: string;
    components: PeerComponent[];
    componentScores: Record<string, Record<string, number>>;
    peerDetails: PeerDetails;
    marks: Record<string, MarkInfo>;
    activeVersion?: number | null;
    visible?: boolean;
}>();

const emit = defineEmits<{
    'toggle': [];
    'clear-marks': [detail: {
        submitterId: string;
        gradeableId: string;
        peer: string;
        csrfToken: string;
    }];
    'resolve-version-conflicts': [detail: {
        submitterId: string;
        gradeableId: string;
        peer: string;
        csrfToken: string;
    }];
    'save-component': [detail: {
        submitterId: string;
        gradeableId: string;
        peer: string;
        componentId: string;
        csrfToken: string;
    }];
    'mark-change': [detail: {
        peer: string;
        componentId: string;
    }];
}>();

const selectedPeer = ref(props.peers[0] ?? '');
const checkedMarkOverrides = ref<Record<string, boolean>>({});

function markKey(componentId: string, peer: string, markId: number): string {
    return `${peer}:${componentId}:${markId}`;
}

function toggle() {
    emit('toggle');
}

function peerDisplayName(peer: string): string {
    return props.peerNames?.[peer] ?? peer;
}

function clearMarks() {
    emit('clear-marks', {
        submitterId: props.submitterId ?? '',
        gradeableId: props.gradeableId ?? '',
        peer: selectedPeer.value,
        csrfToken: props.csrfToken ?? '',
    });
}

function resolveVersionConflicts() {
    emit('resolve-version-conflicts', {
        submitterId: props.submitterId ?? '',
        gradeableId: props.gradeableId ?? '',
        peer: selectedPeer.value,
        csrfToken: props.csrfToken ?? '',
    });
}

function saveComponent(componentId: string) {
    emit('save-component', {
        submitterId: props.submitterId ?? '',
        gradeableId: props.gradeableId ?? '',
        peer: selectedPeer.value,
        componentId,
        csrfToken: props.csrfToken ?? '',
    });
}

function onMarkChange(peer: string, componentId: string, markId: number, event: Event) {
    checkedMarkOverrides.value[markKey(componentId, peer, markId)] = (event.target as HTMLInputElement).checked;
    emit('mark-change', {
        peer,
        componentId,
    });
}

function hasVersionConflict(componentId: string, peer: string): boolean {
    return props.peerDetails?.version_conflicts?.[componentId]?.[peer] ?? false;
}

function peerHasVersionConflict(peer: string): boolean {
    return props.components.some((component) => hasVersionConflict(component.id, peer));
}

function gradedVersion(componentId: string, peer: string): number | undefined {
    return props.peerDetails?.graded_versions?.[componentId]?.[peer];
}

function isExtraCredit(component: PeerComponent): boolean {
    return component.extra_credit ?? false;
}

function shouldShowBadge(earned: number, max: number, extraCredit: boolean): boolean {
    if (extraCredit) {
        // Extra credit always shows, since even +0 is useful information
        return true;
    }
    if (max > 0) {
        return true;
    }
    // Negative points only show if they were actually earned
    return earned < 0;
}

function badgeClass(earned: number, max: number, extraCredit: boolean): string {
    if (extraCredit) {
        return earned === 0 ? 'gray-background' : 'green-background';
    }
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

function badgeText(earned: number, max: number, extraCredit: boolean): string {
    if (extraCredit) {
        return `+${earned}`;
    }
    if (earned < 0) {
        if (max !== 0) {
            return `\u2212${Math.abs(earned)} / ${max}`;
        }
        return `\u2212${Math.abs(earned)}`;
    }
    return `${earned} / ${max}`;
}

function isMarkAssigned(componentId: string, peer: string, markId: number): boolean {
    const key = markKey(componentId, peer, markId);
    if (key in checkedMarkOverrides.value) {
        return checkedMarkOverrides.value[key];
    }
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
    @toggle="toggle"
  >
    <template #trigger>
      <button
        type="button"
        class="btn btn-primary"
        title="Edit Peer Components"
        aria-label="Edit Peer Components"
        data-testid="edit-peer-trigger"
        @click="toggle"
      >
        <i
          class="fas fa-pencil-alt"
          aria-hidden="true"
        />
        Edit Peer Components
      </button>
    </template>
    <template #default>
      <span data-testid="warning-text">
        Select the peer grader whose grades you want to edit.
        <br>
        WARNING: clearing a peer grader's marks will remove all of the peer grading done by that student.
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
          {{ peerDisplayName(peer) }}
        </option>
      </select>
      <div
        v-for="peer in peers"
        v-show="selectedPeer === peer"
        :id="'edit-peer-components-form-' + peer"
        :key="peer"
        class="edit-peer-components-block"
        data-testid="peer-block"
      >
        <button
          type="button"
          class="btn btn-danger"
          title="Delete all grading by this peer grader"
          data-testid="clear-peer-marks"
          @click="clearMarks"
        >
          Delete All Grading by Peer Grader {{ peer }} for Student {{ submitterId }}'s Submission
        </button>
        <button
          v-if="peerHasVersionConflict(peer)"
          type="button"
          class="btn clear-peer-version-conflicts"
          :data-peer-id="peer"
          title="Update the version for all components without inspecting each one"
          data-testid="clear-version-conflicts"
          @click="resolveVersionConflicts"
        >
          Clear Version Conflicts
        </button>
        <br>
        <div
          v-for="component in components"
          :key="component.id"
          class="peer-edit-component"
          :data-testid="'component-block-' + component.id"
        >
          <div
            v-if="hasScore(component.id, peer)"
            class="box-badge"
            data-testid="box-badge"
          >
            <span
              v-if="shouldShowBadge(scoreForComponent(component.id, peer) ?? 0, component.max, isExtraCredit(component))"
              class="badge"
              :class="badgeClass(scoreForComponent(component.id, peer) ?? 0, component.max, isExtraCredit(component))"
              data-testid="score-pill-badge"
            >
              {{ badgeText(scoreForComponent(component.id, peer) ?? 0, component.max, isExtraCredit(component)) }}
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
            v-if="hasVersionConflict(component.id, peer)"
            class="version-warning peer-edit-version-warning"
            :data-component-id="component.id"
            :data-peer-id="peer"
            data-testid="version-warning"
          >
            Version Conflict: {{ peer }} graded version {{ gradedVersion(component.id, peer) }},
            but version {{ activeVersion }} is active.
          </div>
          <div
            class="received-marks-list peer-edit-marks-list container"
            data-testid="marks-list"
          >
            <div
              v-for="markId in component.marks"
              :key="markId"
              class="row"
              :data-testid="'mark-row-' + markId"
            >
              <div class="col-no-gutters indicator">
                <input
                  type="checkbox"
                  class="peer-edit-mark"
                  :data-component-id="component.id"
                  :data-peer-id="peer"
                  :value="markId"
                  :checked="isMarkAssigned(component.id, peer, markId)"
                  data-testid="mark-checkbox"
                  @change="onMarkChange(peer, component.id, markId, $event)"
                >
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
          <button
            type="button"
            class="btn peer-save-component"
            :data-component-id="component.id"
            :data-peer-id="peer"
            title="Save the selected marks for this component"
            data-testid="save-peer-component"
            @click="saveComponent(component.id)"
          >
            Save Component
          </button>
          <span
            class="peer-component-save-status"
            :data-component-id="component.id"
            :data-peer-id="peer"
          />
          <br>
        </div>
      </div>
    </template>
  </Popup>
</template>
