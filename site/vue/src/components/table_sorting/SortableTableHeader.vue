<script setup lang="ts">
import { colDataTypes } from '../../../../ts/sort-table-by-column';

const props = defineProps<{
    tableId: string;
    title: string;
    sortKey: string;
    colDataType: colDataTypes;
    usingRowGroups: boolean; // specifically for statPage.twig's forum post collapsible rows
}>();

const emit = defineEmits<{
    'sort-table-column-click': [payload: { tableId: string; sortKey: string; colDataType: colDataTypes; usingRowGroups: boolean }];
}>();

function handleClick() {
    emit('sort-table-column-click', {
        tableId: props.tableId,
        sortKey: props.sortKey,
        colDataType: props.colDataType,
        usingRowGroups: props.usingRowGroups,
    });
}
</script>

<template>
  <!-- See TableSortComponents.twig -->
  <a
    href="#"
    class="sortable-header"
    :title="'Sort by ' + title"
    :aria-label="'Sort by ' + title"
    :data-sort-key="sortKey"
    @click.prevent="handleClick"
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
