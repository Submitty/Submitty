<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    algorithms: Record<string, { name: string; description: string }>;
    currentAlgorithm?: string;
    createClusteringUrl: string;
    checkClusteringStatusUrl: string;
    csrfToken: string;
    canCreateClustering: boolean;
    gradeableId: string;
    availableDockerImages: string[];
}>();

const emit = defineEmits<{
    'clustering-status': [status: string];
    'clustering-done': [];
    'clustering-error': [message: string];
}>();

const selectedAlgorithm = ref(props.currentAlgorithm || '');
const showModal = ref(false);
const useCustomUpload = ref(false);
const customFile = ref<File | null>(null);
const customDockerImage = ref(props.availableDockerImages[0] ?? '');
const fileInput = ref<HTMLInputElement | null>(null);

function toggleModal() {
    showModal.value = !showModal.value;
    if (!showModal.value) {
        selectedAlgorithm.value = props.currentAlgorithm || '';
        useCustomUpload.value = false;
        customFile.value = null;
    }
}

function onCustomFileChange() {
    if (fileInput.value && fileInput.value.files && fileInput.value.files.length > 0) {
        const file = fileInput.value.files[0];
        if (!file.name.endsWith('.py')) {
            emit('clustering-error', 'Please upload a Python (.py) file.');
            clearCustomFile();
            fileInput.value.value = '';
            return;
        }
        customFile.value = file;
    }
}

function clearCustomFile() {
    customFile.value = null;
}

async function submitClustering() {
    if (!useCustomUpload.value && !selectedAlgorithm.value) {
        return;
    }
    if (useCustomUpload.value && (!customFile.value || !customDockerImage.value)) {
        return;
    }

    showModal.value = false;
    emit('clustering-status', 'fetching');
    const formData = new FormData();
    formData.append('csrf_token', props.csrfToken);

    if (useCustomUpload.value) {
        formData.append('algorithm', 'custom_upload');
        if (customFile.value) {
            formData.append('custom_script', customFile.value);
        }
        formData.append('docker_image', customDockerImage.value);
    } else {
        formData.append('algorithm', selectedAlgorithm.value);
    }

    try {
        const response = await fetch(props.createClusteringUrl, {
            method: 'POST',
            body: formData,
        });

        const result = (await response.json()) as { status: string; message?: string };
        if (result.status === 'success') {
            let pollCount = 0;
            const MAX_POLL_COUNT = 130; // 2 minute 10 seconds timeout
            const pollInterval = setInterval(async () => {
                pollCount++;
                if (pollCount > MAX_POLL_COUNT) {
                    clearInterval(pollInterval);
                    emit('clustering-status', 'error');
                    emit('clustering-error', 'Clustering timed out. Check server logs for details.');
                    selectedAlgorithm.value = props.currentAlgorithm || '';
                    return;
                }
                try {
                    const statusResponse = await fetch(props.checkClusteringStatusUrl);
                    const statusResult = (await statusResponse.json()) as { status: string; data?: { status: string; error_message?: string }; message?: string };
                    if (statusResult.status === 'success' && statusResult.data && statusResult.data.status === 'done') {
                        clearInterval(pollInterval);
                        emit('clustering-status', 'done');
                        emit('clustering-done');
                    }
                    else if (statusResult.status === 'fail' || (statusResult.data && statusResult.data.status === 'error')) {
                        clearInterval(pollInterval);
                        emit('clustering-status', 'error');
                        emit('clustering-error', statusResult.data?.error_message || statusResult.message || 'Clustering process failed.');
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
    {{ currentAlgorithm ? 'Re-create Clusters' : 'Create Clusters' }}
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
            <h1>{{ currentAlgorithm ? 'Re-create Clusters' : 'Create Clusters' }}</h1>
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
              :disabled="useCustomUpload"
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

            <p
              v-if="selectedAlgorithm && algorithms[selectedAlgorithm]"
              style="margin-top: 15px;"
            >
              {{ algorithms[selectedAlgorithm].description }}
            </p>

            <div class="custom-upload-divider">
              <span>— or upload a custom algorithm —</span>
            </div>

            <div class="custom-upload-section">
              <label class="custom-upload-toggle">
                <input
                  type="checkbox"
                  v-model="useCustomUpload"
                  data-testid="use-custom-upload-checkbox"
                  @change="useCustomUpload ? (selectedAlgorithm = '') : (customFile = null)"
                />
                Use a custom Python script
              </label>

              <div v-if="useCustomUpload" class="custom-upload-container">
                <input
                  type="file"
                  accept=".py"
                  class="form-control"
                  data-testid="custom-script-upload"
                  ref="fileInput"
                  @change="onCustomFileChange"
                />
                <p v-if="customFile" class="file-upload-success">
                  <i class="fas fa-check"></i> {{ customFile.name }}
                </p>
                <div class="docker-image-container">
                  <label class="docker-image-label">Docker Image to use:</label>
                  <select
                    v-if="availableDockerImages.length > 0"
                    v-model="customDockerImage"
                    class="form-control"
                    data-testid="custom-docker-image-select"
                  >
                    <option
                      v-for="image in availableDockerImages"
                      :key="image"
                      :value="image"
                    >
                      {{ image }}
                    </option>
                  </select>
                  <p
                    v-else
                    class="no-docker-images-message"
                    data-testid="no-docker-images-message"
                  >
                    No Docker images are configured on this system. Ask your system
                    administrator to add one before using a custom algorithm.
                  </p>
                </div>
                <p class="custom-upload-help-text">
                  Your script must read <code>input.json</code> and write clusters to <code>output.json</code>
                  in its working directory. It runs with no network access, so it cannot install
                  packages &mdash; choose an image that already contains the libraries you need.
                </p>
              </div>
            </div>

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
                  :disabled="useCustomUpload ? (!customFile || !customDockerImage) : !selectedAlgorithm"
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

.custom-upload-divider {
    text-align: center;
    margin: 15px 0;
    color: #888;
    font-size: 0.9em;
}

.custom-upload-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-weight: 500;
}

.custom-upload-toggle input[type="checkbox"] {
    margin: 0;
}

.custom-upload-section {
    margin-bottom: 15px;
}

.file-upload-success {
    margin-top: 5px;
    color: var(--standard-medium-green, #28a745);
    font-size: 0.9em;
}

.custom-upload-container {
    margin-top: 10px;
}

.docker-image-container {
    margin-top: 10px;
}

.docker-image-label {
    font-size: 0.9em;
    display: block;
    margin-bottom: 5px;
}

.no-docker-images-message {
    color: var(--error-alert-dark-red, #cc0000);
    font-size: 0.9em;
}

.custom-upload-help-text {
    margin-top: 8px;
    font-size: 0.85em;
    color: var(--standard-medium-dark-gray, #666666);
}
</style>
