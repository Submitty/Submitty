<script setup lang="ts">
import { colDataTypes, sortTableByColumn } from '../../../ts/sort-table-by-column';

defineProps<{
    tableId: string;
    title: string;
    sortKey: string;
    colDataType: colDataTypes;
    usingRowGroups: boolean; // specifically for statPage.twig's forum post collapsible rows
}>();

// run when page initializes to restore sort, e.g. after reload
// $(() => {
// export function restoreSort() {
// const table: HTMLElement | null = document.getElementById(tableId);
// if (table === null || table.dataset.sortKey === undefined) {
//     return;
// }
//     const savedsortKey = Cookies.get('pending_gradeable_table_key');
//     const savedDirection = Cookies.get('pending_gradeable_table_direction') || 'ASC';
//     if (savedSort) {
//         applySort(savedSort, savedDirection);
//         updateSortIcons(savedSort, savedDirection);
//     }
// }
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
