<script setup lang="ts">
const { data } = defineProps<{
    data: { [key: string]: number | string | null }[] | null;
}>();

function downloadCsv() {
    if (!data || !data.length) {
        return;
    }
    let csv = '';
    // Extract headers
    const headers = Object.keys(data[0]);
    csv += `${headers.map((h) => `"${h.split('"').join('""')}"`).join(',')}\n`;
    // Extract rows
    for (const row of data) {
        csv += `${headers.map((header) => `"${String(row[header] ?? '').split('"').join('""')}"`).join(',')}\n`;
    }
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'submitty.csv');
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
  <button
    v-if="data && data.length"
    id="download-sql-btn"
    class="btn btn-primary"
    @click="downloadCsv"
  >
    Download CSV
  </button>
</template>
