<script setup lang="ts">
import { runSqlQuery, type ServerResponse } from '../../../ts/sql-toolbox';
const { query } = defineProps<{
    query: string;
}>();
const emit = defineEmits<{
    changeRunQueryResults: [{ [key: string]: number | string | null }[]];
    changeRunQueryError: [message: string | false];
}>();
const runQuery = async () => {
    const result = await runSqlQuery(query) as ServerResponse<{ [key: string]: number | string | null }[]>;
    if (result.status === 'fail') {
        emit('changeRunQueryError', result.message);
        emit('changeRunQueryResults', []);
    }
    else {
        emit('changeRunQueryError', false);
        emit('changeRunQueryResults', result.data);
    }
};
</script>

<template>
  <button
    id="run-sql-btn"
    class="btn btn-primary"
    @click="runQuery"
  >
    Run Query
  </button>
</template>
