<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
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

interface ShowConflictPopupDetail {
    conflicts: ConflictInfo[];
    componentTitle: string;
}

const visible = ref(false);
const conflicts = ref<ConflictInfo[]>([]);
const componentTitle = ref('');
const currentIndex = ref(0);

watch(currentIndex, (idx) => {
    // Triggers template re-render for the new conflict
    currentConflict.value = conflicts.value[idx] ?? null;
});

const currentConflict = ref<ConflictInfo | null>(null);

function handleShowConflictPopup(e: Event) {
    const detail = (e as CustomEvent).detail as ShowConflictPopupDetail;
    conflicts.value = detail.conflicts;
    componentTitle.value = detail.componentTitle;
    currentIndex.value = 0;
    currentConflict.value = detail.conflicts[0] ?? null;
    visible.value = true;
}

function handleConflictResolved() {
    const nextIdx = currentIndex.value + 1;
    if (nextIdx >= conflicts.value.length) {
        visible.value = false;
        currentConflict.value = null;
        window.dispatchEvent(new CustomEvent('all-conflicts-resolved'));
    }
    else {
        currentIndex.value = nextIdx;
    }
}

function resolve(markId: number, resolution: 'dom' | 'server' | 'old-server') {
    window.dispatchEvent(new CustomEvent('resolve-conflict', {
        detail: { markId, resolution },
    }));
}

function close() {
    visible.value = false;
    currentConflict.value = null;
    window.dispatchEvent(new CustomEvent('close-conflict-popup'));
}

onMounted(() => {
    window.addEventListener('show-conflict-popup', handleShowConflictPopup);
    window.addEventListener('conflict-resolved', handleConflictResolved);
});

onUnmounted(() => {
    window.removeEventListener('show-conflict-popup', handleShowConflictPopup);
    window.removeEventListener('conflict-resolved', handleConflictResolved);
});
</script>

<template>
  <Popup
    :visible="visible"
    :title="`Mark Conflicts: ${componentTitle}`"
    @toggle="close"
  >
    <template #trigger>
      <span style="display: none;" />
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
                  data-testid="mark-conflict-old-server-btn"
                  @click="resolve(currentConflict.domMark.id, 'old-server')"
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
                    data-testid="mark-conflict-server-btn"
                    @click="resolve(currentConflict.domMark.id, 'server')"
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
                    data-testid="mark-conflict-server-btn"
                    @click="resolve(currentConflict.domMark.id, 'server')"
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
                    data-testid="mark-conflict-dom-btn"
                    @click="resolve(currentConflict.domMark.id, 'dom')"
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
                    data-testid="mark-conflict-dom-btn"
                    @click="resolve(currentConflict.domMark.id, 'dom')"
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
