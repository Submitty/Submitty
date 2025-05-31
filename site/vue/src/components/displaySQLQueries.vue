<script setup lang="ts">
import { ref } from 'vue';
import Popup from './popup.vue';
import { buildCourseUrl, getCsrfToken, displayErrorMessage } from '../../../ts/utils/server';

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

const showPopup = ref(false);
const queries = ref<QueryEntry[]>([]);

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
        showPopup.value = false;
    }
};

const handleToggle = async () => {
    showPopup.value = !showPopup.value;
    if (showPopup.value) {
        await fetchQueries();
    }
};

const selectQuery = (id: number) => {
    const query = queries.value.find((q) => q.id === id);
    if (query) {
        const textBox = document.getElementById('toolbox-textarea') as HTMLTextAreaElement;
        if (textBox) {
            textBox.value = query.query;
        }
    }
    showPopup.value = false;
};

const deleteQuery = async (id: number) => {
    if (!confirm('Are you sure you want to delete this query?')) {
        return;
    }

    const form = new FormData();
    form.append('csrf_token', getCsrfToken());
    form.append('query_id', id.toString());

    try {
        const response = await fetch(buildCourseUrl(['sql_toolbox', 'queries', 'delete']), {
            method: 'POST',
            body: form,
        });

        if (!response.ok) {
            throw new Error('Failed to delete query');
        }

        await fetchQueries();
    }
    catch (e) {
        console.error('Error deleting query:', e);
    }
};

</script>

<template>
  <Popup
    title="Select a Saved Query"
    :visible="showPopup"
    @toggle="handleToggle"
  >
    <template #trigger>
      <button
        class="btn btn-primary"
        @click="handleToggle"
      >
        Saved Queries
      </button>
    </template>

    <template #default>
      <table
        v-if="queries.length !== 0"
        class="table"
      >
        <thead>
          <tr>
            <th>Query Name</th>
            <th>Query Snippet</th>
            <th>Select</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="query in queries"
            :key="query.id"
          >
            <td class="td-wrap-element">
              {{ query.query_name }}
            </td>
            <td class="td-wrap-element">
              {{ query.query.length > 200 ? query.query.substring(0, 200) + '...' : query.query }}
            </td>
            <td>
              <button
                class="btn btn-sm btn-primary"
                @click="selectQuery(query.id)"
              >
                Select
              </button>
            </td>
            <td>
              <a
                class="fa fa-trash"
                aria-hidden="true"
                @click="deleteQuery(query.id)"
              />
            </td>
          </tr>
        </tbody>
      </table>

      <p v-else>
        No saved queries available.
      </p>
    </template>
  </Popup>
</template>

<style lang="css" scoped>
.td-wrap-element {
  width: 50%;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: break-word;
}
</style>
