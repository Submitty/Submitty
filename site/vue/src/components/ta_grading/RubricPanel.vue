<script setup lang="ts">
import { onMounted, ref } from 'vue';

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

const containerRef = ref<HTMLElement | null>(null);

const editMode = ref(false);
const silentEdit = ref(false);

onMounted(async () =>{
    await (window as any).loadTemplates?.();
    await (window as any).reloadGradingRubric?.(props.gradeableId, props.anonId);
})

const handleVerifyAll = async () => {
    await (window as any).onVerifyAll?.(null);
}

const handleClearConflicts = async () => {
    await (window as any).updateAllComponentVersions?.();
}

const handleEditModeToggle = async () => {
    await (window as any).onToggleEditMode?.();
    editMode.value = !editMode.value;
}

const handleSilentEditToggle = async () => {
    (window as any).updateCookies?.();
    silentEdit.value = !silentEdit.value;
}
</script>

<template>
  <div id="rubric-vue-controls" class="row row-wrap vertical-center">
    <div v-if="showVerifyAll" class="col-no-gutters">
      <button class="btn btn-default key_to_click mx-1" @click="handleVerifyAll">Verify All</button>
    </div>

    <div v-if="showClearConflicts && !versionConflict" class="col-no-gutters">
      <button class="btn btn-default key_to_click mx-1" @click="handleClearConflicts">Clear Version Conflicts</button>
    </div>

    <div v-if="showSilentEdit" class="col-no-gutters">
      <label>
        <input type="checkbox" class="key_to_click" :checked="silentEdit" @change="handleSilentEditToggle" />
        Silent Regrade (don't change who graded)
      </label>
    </div>

    <div v-if="!isPeerGrader" class="col-no-gutters">
      <label>
        <input type="checkbox" class="key_to_click" :checked="editMode" @change="handleEditModeToggle" />
        Edit Rubric
      </label>
    </div>
  </div>
</template>