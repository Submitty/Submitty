<script setup lang="ts">
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'clustering-status', status: string): void;
    (e: 'toggle-clustering-mode', payload: { isClusteringMode: boolean; gradeableId: string }): void;
}>();

const props = defineProps<{
    isClusteringMode: boolean;
    algorithms: Record<string, string>;
    currentAlgorithm?: string;
    createClusteringUrl: string;
    checkClusteringStatusUrl: string;
    csrfToken: string;
    canCreateClustering: boolean;
    gradeableId: string;
}>();

const selectedAlgorithm = ref(props.currentAlgorithm || '');

function toggleClusteringMode() {
    emit('toggle-clustering-mode', {
        isClusteringMode: props.isClusteringMode,
        gradeableId: props.gradeableId,
    });
}

async function onAlgorithmChange(event?: Event) {
    const value = event ? (event.target as HTMLSelectElement).value : selectedAlgorithm.value;
    if (props.isClusteringMode && value) {
        emit('clustering-status', 'fetching');
        const formData = new FormData();
        formData.append('csrf_token', props.csrfToken);
        formData.append('algorithm', value);

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
                        const statusResult = (await statusResponse.json()) as { status: string; data?: { status: string } };
                        if (statusResult.status === 'success' && statusResult.data && statusResult.data.status === 'done') {
                            clearInterval(pollInterval);
                            emit('clustering-status', 'done');
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.set('cluster_mode', '1');
                            window.location.search = urlParams.toString();
                        }
                    }
                    catch (e) {
                        console.error('Error checking clustering status:', e);
                        clearInterval(pollInterval);
                        emit('clustering-status', 'error');
                        alert('Error checking clustering status.');
                    }
                }, 1000);
            }
            else {
                emit('clustering-status', 'done');
                alert(result.message || 'Error creating clusters');
                // Revert selection if it failed
                selectedAlgorithm.value = props.currentAlgorithm || '';
            }
        }
        catch (error) {
            console.error('Error:', error);
            emit('clustering-status', 'error');
            alert('Failed to connect to the server.');
        }
    }
}
</script>

<template>
  <button
    class="btn btn-primary"
    @click="toggleClusteringMode"
  >
    {{ isClusteringMode ? 'Exit Clustering Mode' : 'Go to Clustering Mode' }}
  </button>
  <select
    v-if="isClusteringMode && Object.keys(algorithms).length > 0 && canCreateClustering"
    v-model="selectedAlgorithm"
    class="form-control clustering-select"
    data-testid="clustering-algorithm-select"
    @change="onAlgorithmChange"
  >
    <option
      value=""
      disabled
    >
      Select an algorithm...
    </option>
    <option
      v-for="(name, id) in algorithms"
      :key="id"
      :value="id"
    >
      {{ name }}
    </option>
  </select>
</template>

<style scoped>
.clustering-select {
    width: auto;
    margin: 0;
}
</style>
