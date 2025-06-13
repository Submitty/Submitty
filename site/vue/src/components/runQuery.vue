<script setup lang="ts">
import { runSqlQuery, type SqlQueryResult } from '../../../ts/sql-toolbox';
const { query } = defineProps<{
    query: string;
}>();
const emit = defineEmits<{
    changeData: [{ [key: string]: number | string | null }[] | null];
    changeError: [message: string | false];
}>();
const runQuery = async () => {
    const result = await runSqlQuery(query) as SqlQueryResult;
    if (result.status === 'fail') {
        emit('changeError', result.message);
        emit('changeData', []);
    }
    else {
        emit('changeData', result.data);
        emit('changeError', false);
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
