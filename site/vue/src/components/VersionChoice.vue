<script setup lang="ts">
import type { Versions } from './StudentInformationPanel.vue';

interface Props {
    formatting?: string;
    viewVersionUrl?: string;
    activeVersion: number;
    displayVersion: number;
    versions: Versions;
    totalPoints?: number;
}

const props = withDefaults(defineProps<Props>(), {
    formatting: '',
    viewVersionUrl: undefined,
    totalPoints: 0,
});
const emit = defineEmits<{
    change: [value: number];
}>();

const handleChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    emit('change', parseInt(target.value));
    if (!props.viewVersionUrl) {
        return;
    }
    window.location.href = props.viewVersionUrl + target.value;
};
</script>

<template>
  <select
    id="submission-version-select"
    data-testid="submission-version-select"
    aria-label="Submission Version Select"
    :style="`margin-right: 10px;${formatting}`"
    name="submission_version"
    @change="handleChange"
  >
    <option
      v-if="activeVersion === 0"
      value="0"
      :selected="displayVersion === activeVersion"
    />

    <option
      v-for="(version, versionNum) in versions"
      :key="versionNum"
      :value="versionNum"
      :selected="parseInt(versionNum) === displayVersion"
    >
      Version #{{ versionNum }}
      <template v-if="totalPoints > 0">
        &nbsp;&nbsp;&nbsp;Score: {{ version.points }} / {{ totalPoints }}
      </template>
      <template v-if="version.days_late > 0">
        &nbsp;&nbsp;&nbsp;Days Late: {{ version.days_late }}
      </template>
      <template v-if="parseInt(versionNum) === activeVersion">
        &nbsp;&nbsp;&nbsp;GRADE THIS VERSION
      </template>
    </option>
  </select>
</template>
