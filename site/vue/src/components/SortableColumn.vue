<script setup lang="ts">
import { colDataTypes, sortTableByColumn, restoreSort } from '../../../ts/sort-table-by-column';
import { onMounted } from 'vue';

const props = defineProps<{
    tableId: string;
    title: string;
    sortKey: string;
    colDataType: colDataTypes;
    usingRowGroups: boolean; // specifically for statPage.twig's forum post collapsible rows
}>();

onMounted(() => {
    restoreSort(props.tableId);
});
</script>

<template>
  <!-- See TableHeaderSort.twig -->
  <a
    href="#"
    class="sortable-header"
    :title="'Sort by ' + title"
    :aria-label="'Sort by ' + title"
    :data-sort-key="sortKey"
    @click.prevent="sortTableByColumn(tableId, sortKey, colDataType, usingRowGroups)"
  >
    {{ title }}
    <i class="fa fa-sort" />
  </a>
</template>

<style scoped>
a.sortable-header {
  text-decoration: none;
}
</style>
