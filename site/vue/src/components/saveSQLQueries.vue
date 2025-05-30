<script setup lang="ts">
import { ref } from 'vue';
import Popup from './popup.vue';
import { buildCourseUrl, getCsrfToken } from '../../../ts/utils/server';

interface ServerResult {
    status: string;
    message: string | null;
    data: { [key: string]: number | string | null }[];
}

const showPopup = ref(false);
const queryData = ref({
    query_name: '',
    query: '',
});
const error = ref({
    error: false,
    message: '',
});

const handleToggle = () => {
    showPopup.value = !showPopup.value;

    if (showPopup.value) {
        const textBox = document.getElementById('toolbox-textarea') as HTMLTextAreaElement;
        if (textBox) {
            queryData.value.query = textBox.value;
        }
    }
};

const displayError = (message: string) => {
    error.value.error = true;
    error.value.message = message;
    setTimeout(() => {
        error.value.error = false;
    }, 5000);
};

const handleSave = async () => {
    const form = new FormData();
    form.append('csrf_token', getCsrfToken());
    form.append('query_name', queryData.value.query_name);
    form.append('query', queryData.value.query);

    try {
        const response = await fetch(buildCourseUrl(['sql_toolbox', 'queries']), {
            method: 'POST',
            body: form,
        });

        if (!response.ok) {
            throw new Error('Failed to save query');
        }

        const result = await response.json() as ServerResult;
        if (result.status === 'success') {
            showPopup.value = false;
        }
        else {
            displayError(result.message ?? 'An unknown error occurred while saving the query');
        }
    }
    catch (e) {
        displayError(`An error occurred while saving the query: ${(e as Error).message ?? 'Unknown error'}`);
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
        <input
          type="hidden"
          name="csrf_token"
          :value="getCsrfToken()"
        />

        <label
          id="query-name-label"
          for="query-name"
          class="query-label"
        >Query Name</label>
        <input
          id="query-name"
          v-model="queryData.query_name"
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
          v-model="queryData.query"
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
