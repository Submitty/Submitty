<script setup lang="ts">
const { resultsData, queryError } = defineProps<{
    resultsData: { [key: string]: number | string | null }[] | null;
    queryError: string | false;
}>();
</script>

<template>
  <div>
    <h2>Query Results</h2>
    <div
      v-if="queryError"
      id="query-results-error"
      class="red-message"
    >
      <pre id="query-results-error-message">{{ queryError }}</pre>
    </div>

    <table
      v-else-if="resultsData && resultsData.length > 0"
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

    <p
      v-else-if="!queryError && resultsData && resultsData.length === 0"
    >
      No results found.
    </p>
  </div>
</template>
