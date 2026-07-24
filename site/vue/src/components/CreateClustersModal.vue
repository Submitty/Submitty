<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    algorithms: Record<string, {name: string, description: string}>;
    currentAlgorithm?: string;
    createClusteringUrl: string;
    checkClusteringStatusUrl: string;
    csrfToken: string;
    canCreateClustering: boolean;
    gradeableId: string;
}>();

const emit = defineEmits<{
    'clustering-status': [status: string];
    'clustering-done': [];
    'clustering-error': [message: string];
}>();

const selectedAlgorithm = ref(props.currentAlgorithm || '');
const showModal = ref(false);

function toggleModal() {
    showModal.value = !showModal.value;
    if (!showModal.value) {
        selectedAlgorithm.value = props.currentAlgorithm || '';
    }
}

async function submitClustering() {
    if (!selectedAlgorithm.value) {
        return;
    }

    showModal.value = false;
    emit('clustering-status', 'fetching');
    const formData = new FormData();
    formData.append('csrf_token', props.csrfToken);
    formData.append('algorithm', selectedAlgorithm.value);

    try {
        const response = await fetch(props.createClusteringUrl, {
            method: 'POST',
            body: formData,
        });

        const result = (await response.json()) as { status: string; message?: string };
        if (result.status === 'success') {
            const pollInterval = setInterval(async () => {
                try {
                    const statusResponse = await fetch(props.checkClusteringStatusUrl);
                    const statusResult = (await statusResponse.json()) as { status: string; data?: { status: string }; message?: string };
                    if (statusResult.status === 'success' && statusResult.data && statusResult.data.status === 'done') {
                        clearInterval(pollInterval);
                        emit('clustering-status', 'done');
                        emit('clustering-done');
                    }
                    else if (statusResult.status === 'fail' || (statusResult.data && statusResult.data.status === 'error')) {
                        clearInterval(pollInterval);
                        emit('clustering-status', 'error');
                        emit('clustering-error', statusResult.message || 'Clustering process failed.');
                        selectedAlgorithm.value = props.currentAlgorithm || '';
                    }
                }
                catch (e) {
                    console.error('Error checking clustering status:', e);
                    clearInterval(pollInterval);
                    emit('clustering-status', 'error');
                    emit('clustering-error', 'Error checking clustering status.');
                }
            }, 1000);
        }
        else {
            emit('clustering-status', 'error');
            emit('clustering-error', result.message || 'Error creating clusters');
            selectedAlgorithm.value = props.currentAlgorithm || '';
        }
    }
    catch (error) {
        console.error('Error:', error);
        emit('clustering-status', 'error');
        emit('clustering-error', 'Failed to connect to the server.');
    }
}
</script>

<template>
  <button
    v-if="canCreateClustering"
    class="btn btn-primary"
    data-testid="create-clusters-btn"
    style="margin-left: auto;"
    @click="toggleModal"
  >
    Create Clusters
  </button>

  <Teleport to="body">
    <div
      v-if="showModal"
      class="popup-form"
      style="display: block;"
    >
      <div
        class="popup-box"
        @click.self="toggleModal"
      >
        <div
          class="popup-window"
          style="width: 400px; margin: auto;"
        >
          <div class="form-title">
            <h1>Create Clusters</h1>
            <button
              data-testid="close-button"
              class="btn btn-default close-button"
              type="button"
              @click="toggleModal"
            >
              Close
            </button>
          </div>
          <div class="form-body">
            <p style="margin-bottom: 15px;">
              Select an algorithm to generate clusters for this gradeable.
            </p>
            <select
              v-if="Object.keys(algorithms).length > 0"
              v-model="selectedAlgorithm"
              class="form-control clustering-select"
              data-testid="clustering-algorithm-select"
            >
              <option
                value=""
                disabled
              >
                Select an algorithm...
              </option>
              <option
                v-for="(algo, id) in algorithms"
                :key="id"
                :value="id"
              >
                {{ algo.name }}
              </option>
            </select>
            <div v-else>
              No clustering algorithms available.
            </div>

            <p v-if="selectedAlgorithm && algorithms[selectedAlgorithm]" style="margin-top: 15px;">
              {{ algorithms[selectedAlgorithm].description }}
            </p>

            <div class="form-buttons">
              <div
                class="form-button-container"
                style="justify-content: flex-end; display: flex; gap: 10px;"
              >
                <a
                  class="btn btn-default close-button key_to_click"
                  tabindex="0"
                  @click="toggleModal"
                >
                  Cancel
                </a>
                <button
                  class="btn btn-primary"
                  :disabled="!selectedAlgorithm"
                  @click="submitClustering"
                >
                  Submit
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.clustering-select {
    width: 100%;
}
</style>
