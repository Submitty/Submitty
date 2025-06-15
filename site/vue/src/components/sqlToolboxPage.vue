<script setup lang="ts">
import { ref } from 'vue';
import DisplaySQLQueries from './displaySQLQueries.vue';
import SaveSQLQueries from './saveSQLQueries.vue';
import SqlSchema from './sqlSchema.vue';
import type { QueryListEntry } from '../../../ts/sql-toolbox';
import RunQuery from './runQuery.vue';
import QueryResultsTable from './queryResultsTable.vue';
import DownloadQuery from './downloadQuery.vue';

const { sqlStructureData, userQueriesList } = defineProps<{
    sqlStructureData: {
        name: string;
        columns: {
            name: string;
            type: string;
        }[];
    }[];
    userQueriesList: QueryListEntry[];
}>();

const queries = ref<QueryListEntry[]>(userQueriesList);
const currentQuery = ref({
    query_name: '',
    query: '',
});

function deleteQuery(id: number) {
    queries.value = queries.value.filter((query) => query.id !== id);
}
function addQuery(id: number, query_name: string, query: string) {
    queries.value.push({ id, query_name, query });
}
function changeError(error: boolean, message: string) {
    queryError.value.error = error;
    queryError.value.message = message;
}
function changeData(data: { [key: string]: number | string | null }[]) {
    resultsData.value = data;
}
function addToQuery(query: string) {
    currentQuery.value.query += query;
}

const resultsData = ref<{ [key: string]: number | string | null }[]>([]);
const queryError = ref({
    error: false,
    message: '',
});
</script>

<template>
  <div class="content">
    <h1>SQL Toolbox</h1>

    <div>
      Use this toolbox to run a SELECT query. You cannot run any other type of query, and may only run a single
      query at a time.
      You can download a CSV of the query results. Must Run Query before you can Download.
      <br /><br />
      <SqlSchema
        id="sql-schema"
        :data="sqlStructureData"
      />
      <DisplaySQLQueries
        id="toolbox-display-queries"
        :queries="queries"
        @delete="deleteQuery"
        @add-to-query="addToQuery"
      />

      <textarea
        id="toolbox-textarea"
        v-model="currentQuery.query"
        name="sql"
        style="margin-bottom: 2px;"
        aria-label="Input SQL"
      />

      <div id="query-results-buttons">
        <RunQuery
          :query="currentQuery.query"
          @change-data="changeData"
          @change-error="changeError"
        />
        <SaveSQLQueries
          v-model:data="currentQuery"
          @add="addQuery"
        />
        <DownloadQuery
          :data="resultsData"
        />
      </div>
    </div>
    <QueryResultsTable
      :query-error="queryError"
      :results-data="resultsData"
    />
  </div>
</template>

<style scoped>
#toolbox-display-queries {
  margin-top: 5px;
  margin-bottom: 5px;
}
#query-results-buttons * {
  margin-top: 5px;
  margin-right: 5px;
}
</style>
