<script setup lang="ts">
import { onMounted, ref, toRefs } from 'vue';

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

const containerRef = ref<HTMLElement | null>(null);

const editMode = ref(false);
const silentEdit = ref(false);

onMounted(() => {
  // Data loading is handled by the inline <script> in RubricPanel.twig.
  // This component only manages the panel controls.
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
</template>