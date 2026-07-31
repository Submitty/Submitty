<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Popup from '../Popup.vue';
import { buildCourseUrl, getCsrfToken } from '../../../../ts/utils/server';

interface MarkInfo {
    id: number;
    points: number;
    title: string | null;
    publish: boolean;
}

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

interface ConflictInfo {
    domMark: MarkInfo;
    serverMark: MarkInfo | null;
    oldServerMark: MarkInfo | null;
    localDeleted: boolean;
}

const props = defineProps<{
    conflicts: Record<number, RawConflictInfo>;
    componentTitle: string;
    componentId: number;
    gradeableId: string;
}>();

const emit = defineEmits<{
    'all-resolved': [];
    'close': [];
}>();

const visible = ref(false);
const currentIndex = ref(0);

function buildMarkInfo(mark: RawMark): MarkInfo {
    return { id: mark.id, points: mark.points, title: mark.title ?? null, publish: mark.publish };
}

const conflictsList = computed<ConflictInfo[]>(() =>
    Object.values(props.conflicts).map((c) => ({
        domMark: buildMarkInfo(c.domMark),
        serverMark: c.serverMark ? buildMarkInfo(c.serverMark) : null,
        oldServerMark: c.oldServerMark ? buildMarkInfo(c.oldServerMark) : null,
        localDeleted: c.localDeleted,
    })),
);

const currentConflict = computed(() => conflictsList.value[currentIndex.value] ?? null);

watch(() => props.conflicts, (newConflicts) => {
    if (newConflicts && Object.keys(newConflicts).length > 0) {
        currentIndex.value = 0;
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

// --- AJAX helpers (switched from $.ajax to fetch + FormData per sql-toolbox.ts pattern) ---

async function ajaxAddNewMark(title: string, points: number, publish: boolean) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('component_id', String(props.componentId));
    formData.append('title', title);
    formData.append('points', String(points));
    formData.append('publish', String(publish));

    const resp = await fetch(
        buildCourseUrl(['gradeable', props.gradeableId, 'components', 'marks', 'add']),
        { method: 'POST', body: formData },
    );
    if (!resp.ok) {
        throw new Error(`Server returned ${resp.status}`);
    }
    const response = await resp.json() as { status: string; message: string; data: { mark_id: number } };
    if (response.status !== 'success') {
        throw new Error(response.message);
    }
    return response.data;
}

async function ajaxDeleteMark(mark_id: number) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('component_id', String(props.componentId));
    formData.append('mark_id', String(mark_id));

    const resp = await fetch(
        buildCourseUrl(['gradeable', props.gradeableId, 'components', 'marks', 'delete']),
        { method: 'POST', body: formData },
    );
    if (!resp.ok) {
        throw new Error(`Server returned ${resp.status}`);
    }
    const response = await resp.json() as { status: string; message: string };
    if (response.status !== 'success') {
        throw new Error(response.message);
    }
}

async function ajaxSaveMark(mark_id: number, title: string, points: number, publish: boolean) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('component_id', String(props.componentId));
    formData.append('mark_id', String(mark_id));
    formData.append('title', title);
    formData.append('points', String(points));
    formData.append('publish', String(publish));

    const resp = await fetch(
        buildCourseUrl(['gradeable', props.gradeableId, 'components', 'marks', 'save']),
        { method: 'POST', body: formData },
    );
    if (!resp.ok) {
        throw new Error(`Server returned ${resp.status}`);
    }
    const response = await resp.json() as { status: string; message: string };
    if (response.status !== 'success') {
        throw new Error(response.message);
    }
}

async function handleResolve(markId: number, resolution: 'dom' | 'server' | 'old-server') {
    const conflict = props.conflicts[markId];
    if (!conflict) {
        return;
    }
    try {
        if (resolution === 'dom') {
            if (conflict.localDeleted) {
                await ajaxDeleteMark(markId);
            }
            else {
                const isServerDeleted = conflict.serverMark === null;
                if (isServerDeleted) {
                    const data = await ajaxAddNewMark(conflict.domMark.title!, conflict.domMark.points, conflict.domMark.publish);
                    // Mutates the raw conflict object, which shares references with
                    // saveMarkList's domMarkList so mark order uses the new server id.
                    conflict.domMark.id = data.mark_id;
                }
                else {
                    await ajaxSaveMark(markId, conflict.domMark.title!, conflict.domMark.points, conflict.domMark.publish);
                }
            }
        }
        else if (resolution === 'old-server') {
            const mark = conflict.oldServerMark!;
            await ajaxSaveMark(markId, mark.title!, mark.points, mark.publish);
        }
        // resolution === 'server': accept server state, no AJAX needed
    }
    catch (err) {
        // Keep the popup open so the user can retry; never silently drop a resolution.
        console.error(`Failed to resolve conflict for mark ${markId}:`, err);
        return;
    }

    // Advance to the next conflict, or finish once all conflicts are resolved
    currentIndex.value++;
    if (currentIndex.value >= conflictsList.value.length) {
        visible.value = false;
        emit('all-resolved');
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
