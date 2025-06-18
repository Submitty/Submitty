<script setup lang="ts">
import Popup from './popup.vue';
import { buildCourseUrl, getCsrfToken, displayErrorMessage, displaySuccessMessage } from '../../../ts/utils/server';
import { ref } from 'vue';
import type { QueryListEntry } from '../../../ts/sql-toolbox';

const { queries } = defineProps<{
    queries: QueryListEntry[];
}>();

const emit = defineEmits<{
    delete: [id: number];
    addToQuery: [query: string];
}>();

interface ServerResult {
    status: string;
    message: string | null;
    data: { [key: string]: number | string | null }[];
}

const showPopup = ref(false);

const handleToggle = () => {
    showPopup.value = !showPopup.value;
};

const addQuery = (id: number) => {
    const query = queries.find((q) => q.id === id);
    if (query) {
        emit('addToQuery', query.query);
    }
};

const handleDeletion = async (id: number) => {
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
            throw new Error('Failed to delete query.');
        }

        const json = await response.json() as ServerResult;
        if (json.status === 'fail') {
            throw new Error(json.message ?? 'Failed to delete query.');
        }

        // remove the query from the main ref
        emit('delete', id);
        displaySuccessMessage('Query deleted successfully!');
    }
    catch (e) {
        console.error('Error deleting query:', e);
        displayErrorMessage(`Error deleting query: ${(e as Error).message ?? 'An unknown error occurred while saving the query. Please try again later.'}`);
    }
};

</script>

<template>
  <Popup
    title="Add a Saved Query"
    :visible="showPopup"
    @toggle="handleToggle"
  >
    <template #trigger>
      <button
        id="saved-queries-btn"
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
            <th>Add</th>
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
                @click="addQuery(query.id)"
              >
                Add
              </button>
            </td>
            <td>
              <a
                class="fa fa-trash"
                aria-hidden="true"
                @click="handleDeletion(query.id)"
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
#saved-queries-btn {
  margin-top: 5px;
  margin-bottom: 5px;
}
.td-wrap-element {
  width: 50%;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: break-word;
}
</style>
