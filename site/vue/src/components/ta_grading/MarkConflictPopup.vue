<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Popup from '../Popup.vue';

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
    conflicts: ConflictInfo[];
    componentTitle: string;
    currentIndex: number;
}>();

const emit = defineEmits<{
    resolve: [{ markId: number; resolution: 'dom' | 'server' | 'old-server' }];
    close: [];
}>();

const visible = ref(false);

const currentConflict = computed(() => props.conflicts[props.currentIndex] ?? null);

watch(() => props.conflicts, (newConflicts) => {
    if (newConflicts.length > 0) {
        visible.value = true;
    }
}, { immediate: true });

function toggle() {
    const wasVisible = visible.value;
    visible.value = !visible.value;
    if (wasVisible) {
        emit('close');
    }
}

function handleResolve(markId: number, resolution: 'dom' | 'server' | 'old-server') {
    const conflict = currentConflict.value;
    if (conflict) {
        emit('resolve', { markId, resolution });
    }
}
</script>

<template>
  <Popup
    :visible="visible"
    :title="`Mark Conflicts: ${componentTitle}`"
    @toggle="toggle"
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
            <div
              v-if="currentConflict.oldServerMark"
              class="row mark-resolve mark-resolve-old-server"
              data-testid="mark-conflict-old-server"
            >
              <span
                class="col"
                data-testid="mark-conflict-old-server-info"
              >
                ({{ currentConflict.oldServerMark.points }}) {{ currentConflict.oldServerMark.title ?? '' }}
                <template v-if="currentConflict.oldServerMark.publish">
                  -- <i>Show mark to all students</i>
                </template>
              </span>
              <span class="col-no-gutters button-container">
                <input
                  type="button"
                  class="btn btn-default"
                  value="Revert to Original"
                  title="Revert to original mark"
                  data-testid="mark-conflict-old-server-btn"
                  @click="handleResolve(currentConflict.domMark.id, 'old-server')"
                >
              </span>
            </div>
            <!-- Current server mark -->
            <div
              class="row mark-resolve mark-resolve-server"
              data-testid="mark-conflict-server"
            >
              <template v-if="currentConflict.serverMark">
                <span
                  class="col"
                  data-testid="mark-conflict-server-info"
                >
                  ({{ currentConflict.serverMark.points }}) {{ currentConflict.serverMark.title ?? '' }}
                  <template v-if="currentConflict.serverMark.publish">
                    -- <i>Show mark to all students</i>
                  </template>
                </span>
                <span class="col-no-gutters button-container">
                  <input
                    type="button"
                    class="btn btn-primary"
                    value="Ignore My Edits"
                    title="Ignore my edits, keep server version"
                    data-testid="mark-conflict-server-btn"
                    @click="handleResolve(currentConflict.domMark.id, 'server')"
                  >
                </span>
              </template>
              <template v-else>
                <span
                  class="col mark-deleted-message"
                  data-testid="mark-conflict-server-deleted"
                >Mark Deleted From Server</span>
                <span class="col-no-gutters button-container">
                  <input
                    type="button"
                    class="btn btn-primary"
                    value="Delete Mark"
                    title="Delete the mark from server"
                    data-testid="mark-conflict-server-btn"
                    @click="handleResolve(currentConflict.domMark.id, 'server')"
                  >
                </span>
              </template>
            </div>
            <!-- Local (DOM) mark -->
            <div
              class="row mark-resolve mark-resolve-dom"
              data-testid="mark-conflict-dom"
            >
              <template v-if="!currentConflict.localDeleted">
                <span
                  class="col"
                  data-testid="mark-conflict-dom-info"
                >
                  ({{ currentConflict.domMark.points }}) {{ currentConflict.domMark.title ?? '' }}
                  <template v-if="currentConflict.domMark.publish">
                    -- <i>Show mark to all students</i>
                  </template>
                </span>
                <span class="col-no-gutters button-container">
                  <input
                    type="button"
                    class="btn btn-primary"
                    value="Use My Edits"
                    title="Use my local edits"
                    data-testid="mark-conflict-dom-btn"
                    @click="handleResolve(currentConflict.domMark.id, 'dom')"
                  >
                </span>
              </template>
              <template v-else>
                <span
                  class="col mark-deleted-message"
                  data-testid="mark-conflict-dom-deleted"
                >You Deleted the Mark</span>
                <span class="col-no-gutters button-container">
                  <input
                    type="button"
                    class="btn btn-primary"
                    value="Delete Mark"
                    title="Delete the mark"
                    data-testid="mark-conflict-dom-btn"
                    @click="handleResolve(currentConflict.domMark.id, 'dom')"
                  >
                </span>
              </template>
            </div>
          </div>
        </div>
        <div
          v-if="conflicts.length > 1"
          class="conflict-resolve-progress"
          data-testid="mark-conflict-progress"
        >
          <i><span class="conflict-resolve-progress-indicator">{{ currentIndex + 1 }}</span> out of {{ conflicts.length }}</i>
        </div>
      </div>
    </template>
  </Popup>
</template>

<style scoped>
.hidden-trigger {
    display: none;
}

.mark-conflict-row span {
    padding: 3px;
    border-width: 2px;
    margin: 0;
    display: inline-flex;
    align-items: center;
}

.mark-conflict-row .button-container {
    width: 150px;
}

.mark-conflict-row .button-container input {
    width: 100%;
}

.conflict-resolve-progress {
    width: fit-content;
    margin: 0 auto;
}

.mark-conflict-container {
    margin-top: 25px;
}
</style>
