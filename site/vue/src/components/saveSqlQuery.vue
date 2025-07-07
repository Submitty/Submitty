<script setup lang="ts">
import { ref } from 'vue';
import Popup from './popup.vue';
import { saveSqlQuery, type ServerResponse } from '../../../ts/sql-toolbox';

const data = defineModel<{
    query_name: string;
    query: string;
}>('data', { required: true });

const emit = defineEmits<{
    addSavedQuery: [id: number, query_name: string, query: string];
}>();

const showPopup = ref(false);
const error = ref({
    error: false,
    message: '',
});

const displayError = (message: string) => {
    error.value.error = true;
    error.value.message = message;
    setTimeout(() => {
        error.value.error = false;
    }, 5000);
};

const handleToggle = () => {
    showPopup.value = !showPopup.value;
};

const handleSave = async () => {
    const response = await saveSqlQuery(data.value.query_name, data.value.query) as ServerResponse<number>;

    if (response.status === 'success') {
        window.displaySuccessMessage('Query saved successfully!');
        const insertedId: number = response.data;
        emit('addSavedQuery', insertedId, data.value.query_name, data.value.query);
        showPopup.value = false;
        data.value.query_name = '';
        data.value.query = '';
    }
    else {
        displayError(`An error occurred while saving the query: ${response.message ?? 'An unknown error occurred while saving the query. Please try again later.'}`);
    }
};
</script>

<template>
  <Popup
    title="Save Query"
    :visible="showPopup"
    savable
    @toggle="handleToggle"
    @save="handleSave"
  >
    <template #trigger>
      <button
        id="save-query-btn"
        class="btn btn-primary"
        @click="handleToggle"
      >
        Save Query
      </button>
    </template>

    <template #default>
      <Transition name="fade-error">
        <div
          v-if="error.error"
          class="alert alert-danger"
        >
          {{ error.message }}
        </div>
      </Transition>
      <div
        id="form"
        ref="formElement"
        class="form-group"
      >
        <label
          id="query-name-label"
          for="query-name"
          class="query-label"
        >Query Name</label>
        <input
          id="query-name"
          v-model="data.query_name"
          name="query-name"
          type="text"
          maxlength="255"
          placeholder="Enter a name for your query"
          required
        />

        <label
          id="query-text-label"
          for="query-text"
          class="query-label"
        >Query</label>
        <textarea
          id="query-text"
          v-model="data.query"
          name="query"
          rows="6"
          placeholder="Edit your query here"
          required
        />
      </div>
    </template>
  </Popup>
</template>

<style lang="css" scoped>
#save-query-btn
{
  margin-top: 5px;
  margin-right: 5px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

#query-name {
  width: 100%;
}

#query-text {
  width: 100%;
  min-height: 300px;
  resize: vertical;
}

#query-text-label {
  margin-top: 10px;
}

.fade-error-enter-active,
.fade-error-leave-active {
  transition: opacity 0.5s ease;
}
.fade-error-enter-from,
.fade-error-leave-to {
  opacity: 0;
}
</style>
