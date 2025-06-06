<script setup lang="ts">
import { buildCourseUrl, getCsrfToken } from '../../../ts/utils/server';

const { query } = defineProps<{
    query: string;
}>();

const emit = defineEmits<{
    changeData: [{ [key: string]: number | string | null }[]];
    changeError: [error: boolean, message: string];
}>();

type ServerResult = {
    status: string;
    message: string | null;
    data: { [key: string]: number | string | null }[];
};

const runQuery = async () => {
    emit('changeError', false, '');
    emit('changeData', []);
    const form = new FormData();
    form.append('csrf_token', getCsrfToken());
    form.append('sql', query);

    try {
        const response = await fetch(buildCourseUrl(['sql_toolbox']), {
            method: 'POST',
            body: form,
        });

        if (!response.ok) {
            throw new Error('Failed to run query.');
        }

        const result = await response.json() as ServerResult;
        if (result.status !== 'success') {
            throw new Error(result.message || 'Unknown error occurred.');
        }
        emit('changeData', result.data);
    }
    catch (e) {
        emit('changeError', true, (e as Error).message || 'An error occurred while running the query.');
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
