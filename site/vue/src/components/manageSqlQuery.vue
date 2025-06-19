<script setup lang="ts">
import Popup from './popup.vue';
import { displayErrorMessage, displaySuccessMessage } from '../../../ts/utils/server';
import { ref } from 'vue';
import { deleteSqlQuery, type QueryListEntry, type ServerResponse } from '../../../ts/sql-toolbox';

const { queries } = defineProps<{
    queries: QueryListEntry[];
}>();

const emit = defineEmits<{
    deleteSavedQuery: [id: number];
    addCurrentQuery: [query: string];
}>();

const showPopup = ref(false);

const handleToggle = () => {
    showPopup.value = !showPopup.value;
};

const addQuery = (id: number) => {
    const query = queries.find((q) => q.id === id);
    if (query) {
        emit('addCurrentQuery', query.query);
        showPopup.value = false;
    }
};

const handleDeletion = async (id: number) => {
    if (!confirm('Are you sure you want to delete this query?')) {
        return;
    }

    const response = await deleteSqlQuery(id) as ServerResponse<string>;

    if (response.status === 'success') {
        emit('deleteSavedQuery', id);
        displaySuccessMessage('Query deleted successfully!');
    }
    else {
        console.error('Error deleting query:', response.message);
        displayErrorMessage(`Error deleting query: ${response.message}`);
    }
};

</script>

<template>
  <Popup
    title="Add or Delete a Saved Query"
    :visible="showPopup"
    @toggle="handleToggle"
  >
    <template #trigger>
      <button
        id="saved-queries-btn"
        class="btn btn-primary"
        @click="handleToggle"
      >
        Manage Saved Queries
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
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: break-word;
}
</style>
