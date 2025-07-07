<script setup lang="ts">
import { ref } from 'vue';
import SaveSQLQueries from './saveSqlQuery.vue';
import SqlSchema from './sqlSchema.vue';
import RunQuery from './runQuery.vue';
import DownloadQuery from './downloadQuery.vue';
import type { QueryListEntry, RunQueryResults } from '../../../ts/sql-toolbox';
import DisplayQueryResults from './displayQueryResults.vue';
import ManageSqlQuery from './manageSqlQuery.vue';

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

const runQueryResults = ref<RunQueryResults | null>(null);
const runQueryError = ref<string | false>(false);
const currentQuery = ref({
    query_name: '',
    query: '',
});
const savedQueries = ref<QueryListEntry[]>(userQueriesList);

function changeRunQueryError(message: string | false) {
    runQueryError.value = message;
}
function changeRunQueryResults(data: RunQueryResults | null) {
    runQueryResults.value = data;
}

function deleteSavedQuery(id: number) {
    savedQueries.value = savedQueries.value.filter((query) => query.id !== id);
}
function addSavedQuery(id: number, query_name: string, query: string) {
    savedQueries.value.push({ id, query_name, query });
}

function addCurrentQuery(query: string) {
    currentQuery.value.query += query;
}

</script>

<template>
  <div class="content">
    <h1>SQL Toolbox</h1>

    <div>
      <div id="toolbox-info">
        Use this toolbox to run a SELECT query. You cannot run any other type of query, and may only run a single
        query at a time.
        You can download a CSV of the query results. Must Run Query before you can Download.
      </div>
      <SqlSchema
        id="sql-schema"
        :data="sqlStructureData"
      />
      <ManageSqlQuery
        :queries="savedQueries"
        @delete-saved-query="deleteSavedQuery"
        @add-current-query="addCurrentQuery"
      />

      <textarea
        id="toolbox-textarea"
        v-model="currentQuery.query"
        name="sql"
        aria-label="Input SQL"
      />

      <div id="query-results-buttons">
        <RunQuery
          id="run-sql-btn"
          :query="currentQuery.query"
          @change-run-query-error="changeRunQueryError"
          @change-run-query-results="changeRunQueryResults"
        />
        <SaveSQLQueries
          id="save-query-btn"
          v-model:data="currentQuery"
          @add-saved-query="addSavedQuery"
        />
        <DownloadQuery
          v-if="runQueryResults && runQueryResults.length > 0 && !runQueryError"
          id="download-query-btn"
          :data="runQueryResults"
        />
      </div>
    </div>
    <DisplayQueryResults
      :query-error="runQueryError"
      :results-data="runQueryResults"
    />
  </div>
</template>

<style lang="css" scoped>
#run-sql-btn,
#download-query-btn {
  margin-top: 5px;
  margin-right: 5px;
}
#toolbox-textarea {
  margin-bottom: 2px;
}
#sql-schema {
  margin-bottom: 5px;
}
#toolbox-info {
  margin-bottom: 10px;
}

#toolbox-textarea {
  width: 100%;
  min-height: 300px;
  resize: vertical;
}
</style>
