<script setup lang="ts">
const { resultsData, queryError } = defineProps<{
    resultsData: { [key: string]: number | string | null }[];
    queryError: { error: boolean; message: string };
}>();
</script>

<template>
  <div>
    <h2>Query Results</h2>
    <div
      v-if="queryError.error"
      id="query-results-error"
      class="red-message"
    >
      <p>
        {{ queryError.message }}
      </p>
    </div>
    <table
      v-if="resultsData.length"
      class="table table-striped mobile-table"
    >
      <thead>
        <tr>
          <td>#</td>
          <td
            v-for="(val, key) in resultsData[0]"
            :key="key"
          >
            {{ key }}
          </td>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, idx) in resultsData"
          :key="idx"
        >
          <td>{{ idx + 1 }}</td>
          <td
            v-for="(val, key) in row"
            :key="key"
          >
            {{ val !== null ? val : '' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
