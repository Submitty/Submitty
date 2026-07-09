<script setup lang="ts">
import { restoreSort } from '../ts/sort-table-by-column';
import { onMounted } from 'vue';

const props = defineProps<{
    tableId: string;
}>();

onMounted(() => {
    window.addEventListener('DOMContentLoaded', () => {
        restoreSort(props.tableId);
    });
});
</script>

<template>
  <!-- See TableSortComponents.twig -->
  <div />
  <!-- This renderless component is a placeholder for more
   complex logic that will be required when adding multisortable columns.
   In this PR, this component acts only to restore saved sorting data on page load. -->
</template>
