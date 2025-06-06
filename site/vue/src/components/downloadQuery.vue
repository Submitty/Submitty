<script setup lang="ts">
const { data } = defineProps<{
    data: { [key: string]: number | string | null }[];
}>();

function downloadCsv() {
    if (!data.length) {
        return;
    }

    let csv = '';

    // Extract headers
    const headers = Object.keys(data[0]);
    csv += `${headers.map((h) => `"${h.replace(/"/g, '""')}"`).join(',')}\n`;

    // Extract rows
    for (const row of data) {
        csv += `${headers
            .map((h) => {
                const val = row[h];
                return `"${val !== null ? String(val).replace(/"/g, '""') : ''}"`;
            })
            .join(',')}\n`;
    }

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'submitty.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

</script>

<template>
  <button
    v-if="data.length"
    id="download-sql-btn"
    class="btn btn-primary"
    @click="downloadCsv"
  >
    Download CSV
  </button>
</template>
