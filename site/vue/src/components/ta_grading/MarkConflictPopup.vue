<script setup lang="ts">
import { computed } from 'vue';
import Popup from '../Popup.vue';
import MarkConflictOption from './MarkConflictOption.vue';

type MarkConflictResolution = 'dom' | 'server' | 'old-server';

interface RawMark {
    id: number;
    points: number;
    title: string | undefined;
    publish: boolean;
}

interface RawConflictInfo {
    domMark: RawMark;
    serverMark: RawMark | null;
    oldServerMark: RawMark | null;
    localDeleted: boolean;
}

interface MarkInfo {
    id: number;
    points: number;
    title: string | null;
    publish: boolean;
}

interface ConflictInfo {
    domMark: MarkInfo;
    serverMark: MarkInfo | null;
    oldServerMark: MarkInfo | null;
    localDeleted: boolean;
}

const props = defineProps<{
    conflicts: Record<number, RawConflictInfo>;
    componentTitle: string;
    currentIndex?: number;
}>();

const emit = defineEmits<{
    resolve: [payload: { markId: number; resolution: MarkConflictResolution }];
    close: [];
}>();

function buildMarkInfo(mark: RawMark): MarkInfo {
    return { id: mark.id, points: mark.points, title: mark.title ?? null, publish: mark.publish };
}

const conflictsList = computed<ConflictInfo[]>(() =>
    Object.values(props.conflicts ?? {}).map((c) => ({
        domMark: buildMarkInfo(c.domMark),
        serverMark: c.serverMark ? buildMarkInfo(c.serverMark) : null,
        oldServerMark: c.oldServerMark ? buildMarkInfo(c.oldServerMark) : null,
        localDeleted: c.localDeleted,
    })),
);

const currentIndex = computed(() => props.currentIndex ?? 0);
const currentConflict = computed(() => conflictsList.value[currentIndex.value] ?? null);
const visible = computed(() => conflictsList.value.length > 0);

function onResolve(resolution: MarkConflictResolution) {
    const conflict = currentConflict.value;
    if (conflict) {
        emit('resolve', { markId: conflict.domMark.id, resolution });
    }
}

function onToggle() {
    if (visible.value) {
        emit('close');
    }
}
</script>

<template>
  <Popup
    :visible="visible"
    :title="`Mark Conflicts: ${componentTitle}`"
    @toggle="onToggle"
  >
    <template #trigger>
      <span class="hidden-trigger" />
    </template>
    <template #default>
      <h4 data-testid="mark-conflict-description">
        It looks like someone else also edited the rubric. Choose the changes you want to keep.
      </h4>
      <div
        v-if="currentConflict"
        class="container mark-conflict-container"
        data-testid="mark-conflict-container"
      >
        <div class="row mark-conflict-row">
          <div class="col container">
            <!-- Old server mark -->
            <MarkConflictOption
              v-if="currentConflict.oldServerMark"
              :mark="currentConflict.oldServerMark"
              button-label="Revert to Original"
              button-title="Revert to original mark"
              button-style="default"
              testid="mark-conflict-old-server"
              resolution="old-server"
              @resolve="onResolve"
            />
            <!-- Current server mark -->
            <MarkConflictOption
              :mark="currentConflict.serverMark"
              :deleted-message="currentConflict.serverMark ? '' : 'Mark Deleted From Server'"
              :button-label="currentConflict.serverMark ? 'Ignore My Edits' : 'Delete Mark'"
              :button-title="currentConflict.serverMark ? 'Ignore my edits, keep server version' : 'Delete the mark from server'"
              testid="mark-conflict-server"
              resolution="server"
              @resolve="onResolve"
            />
            <!-- Local (DOM) mark -->
            <MarkConflictOption
              :mark="currentConflict.localDeleted ? null : currentConflict.domMark"
              :deleted-message="currentConflict.localDeleted ? 'You Deleted the Mark' : ''"
              :button-label="currentConflict.localDeleted ? 'Delete Mark' : 'Use My Edits'"
              :button-title="currentConflict.localDeleted ? 'Delete the mark' : 'Use my local edits'"
              testid="mark-conflict-dom"
              resolution="dom"
              @resolve="onResolve"
            />
          </div>
        </div>
        <div
          v-if="conflictsList.length > 1"
          class="conflict-resolve-progress"
          data-testid="mark-conflict-progress"
        >
          <i><span class="conflict-resolve-progress-indicator">{{ currentIndex + 1 }}</span> out of {{ conflictsList.length }}</i>
        </div>
      </div>
    </template>
  </Popup>
</template>

<style scoped>
.hidden-trigger {
    display: none;
}

.mark-conflict-row {
    margin-top: 5px;
    margin-bottom: 5px;
}

.conflict-resolve-progress {
    width: fit-content;
    margin: 0 auto;
}

.mark-conflict-container {
    margin-top: 25px;
}
</style>
