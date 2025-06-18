<script setup lang="ts">
import { ref } from 'vue';
import DisplaySQLQueries from './displaySQLQueries.vue';
import SaveSQLQueries from './saveSQLQueries.vue';
import SqlSchema from './sqlSchema.vue';
import RunQuery from './runQuery.vue';
import DownloadQuery from './downloadQuery.vue';
import type { QueryListEntry } from '../../../ts/sql-toolbox';
import DisplayQueryResults from './displayQueryResults.vue';

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
const resultsData = ref<{ [key: string]: number | string | null }[] | null>(null);
const queryError = ref<string | false>(false);
const currentQuery = ref({
    query_name: '',
    query: '',
});

function changeError(message: string | false) {
    queryError.value = message;
}
function changeData(data: { [key: string]: number | string | null }[]) {
    resultsData.value = data;
}

function deleteQuery(id: number) {
    queries.value = queries.value.filter((query) => query.id !== id);
}
function addQuery(id: number, query_name: string, query: string) {
    queries.value.push({ id, query_name, query });
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
      <DisplaySQLQueries
        :queries="queries"
        @delete="deleteQuery"
        @add-query="addQuery"
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
          @change-data="changeData"
          @change-error="changeError"
        />
        <SaveSQLQueries
          id="save-query-btn"
          v-model:data="currentQuery"
          @add="addQuery"
        />
        <DownloadQuery
          v-if="resultsData && resultsData.length > 0 && !queryError"
          id="download-query-btn"
          :data="resultsData"
        />
      </div>
    </div>
    <DisplayQueryResults
      :query-error="queryError"
      :results-data="resultsData"
    />
  </div>
</template>

<style lang="css">
#run-sql-btn,
#download-query-btn,
#save-query-btn
{
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
