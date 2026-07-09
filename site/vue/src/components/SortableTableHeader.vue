<script setup lang="ts">
import { colDataTypes, sortTableByColumn } from '../ts/sort-table-by-column';

defineProps<{
    tableId: string;
    title: string;
    sortKey: string;
    colDataType: colDataTypes;
    usingRowGroups: boolean; // specifically for statPage.twig's forum post collapsible rows
}>();
</script>

<template>
  <!-- See TableSortComponents.twig -->
  <a
    href="#"
    class="sortable-header"
    scope="col"
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

a.sortable-header.active-sort {
  /* "double bold" effect to stand out against regular th bold */
  text-shadow: 0.75px 0;
}
</style>
