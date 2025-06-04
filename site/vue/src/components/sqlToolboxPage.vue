<script setup lang="ts">
import { ref } from 'vue';
import DisplaySQLQueries from './displaySQLQueries.vue';
import SaveSQLQueries from './saveSQLQueries.vue';
import { buildCourseUrl, displayErrorMessage } from '../../../ts/utils/server';

const { sqlStructureData } = defineProps<{
    sqlStructureData: {
        name: string;
        columns: {
            name: string;
            type: string;
        }[];
    }[];
}>();

const queries = ref<QueryEntry[]>([]);

interface QueryEntry {
    id: number;
    query_name: string;
    query: string;
}

interface ServerResult {
    status: string;
    message: string | null;
    data: QueryEntry[];
}

const fetchQueries = async () => {
    try {
        const response = await fetch(buildCourseUrl(['sql_toolbox', 'queries']));
        if (!response.ok) {
            throw new Error('Failed to fetch queries');
        }
        const result = await response.json() as ServerResult;
        queries.value = result.data || [];
    }
    catch (e) {
        displayErrorMessage(`Error fetching queries: ${e instanceof Error ? e.message : 'Unknown error'}`);
        console.error('Error fetching queries:', e);
    }
};

await fetchQueries();
</script>

<template>
  <div class="content">
    <h1>SQL Toolbox</h1>

    <div>
      Use this toolbox to run a SELECT query. You cannot run any other type of query, and may only run a single
      query at a time.
      You can download a CSV of the query results. Must Run Query before you can Download.
      <br /><br />

      <div>
        <div class="database-schema">
          <button
            id="sql-database-schema"
            class="btn btn-primary"
          >
            Database Schema Documentation
          </button>
          <div
            id="sql-database-schema-content"
            hidden
          >
            <p>Click on each table to see columns</p>
            <ul style="margin-left: 30px;">
              <li
                v-for="row in sqlStructureData"
                :key="row.name"
              >
                <a class="sql-database-table">{{ row.name }}</a>
                <div
                  class="sql-database-columns"
                  hidden
                >
                  <ul style="margin-left: 15px">
                    <li
                      v-for="column in row.columns"
                      :key="column.name"
                    >
                      {{ column.name }} - {{ column.type }}
                    </li>
                  </ul>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <DisplaySQLQueries />
      </div>

      <textarea
        id="toolbox-textarea"
        name="sql"
        style="margin-bottom: 2px;"
        aria-label="Input SQL"
      />

      <div id="query-results-buttons">
        <button
          id="run-sql-btn"
          class="btn btn-primary"
        >
          Run Query
        </button>

        <SaveSQLQueries />
        <button
          id="download-sql-btn"
          class="btn btn-primary"
          disabled
        >
          Download CSV
        </button>
      </div>
    </div>

    <div>
      <h2>Query Results</h2>
      <div
        id="query-results-error"
        class="red-message"
      >
        <pre id="query-results-error-message" />
      </div>
      <table
        id="query-results"
        class="table table-striped mobile-table"
      />
    </div>
  </div>
</template>
