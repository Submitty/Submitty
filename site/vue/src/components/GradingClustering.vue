<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    isClusteringMode: boolean;
    algorithms: Record<string, string>;
    currentAlgorithm?: string;
    createClusteringUrl: string;
    csrfToken: string;
    canCreateClustering: boolean;
    gradeableId: string;
}>();

const selectedAlgorithm = ref(props.currentAlgorithm || Object.keys(props.algorithms)[0] || '');

async function toggleClusteringMode() {
    const urlParams = new URLSearchParams(window.location.search);
    if (props.isClusteringMode) {
        urlParams.delete('cluster_mode');
        urlParams.delete('algorithm');
        window.location.search = urlParams.toString();
    } else {
        const hasAcceptedWarning = sessionStorage.getItem('clusteringWarningAccepted_' + props.gradeableId) === 'true';
        if (!hasAcceptedWarning && typeof (window as any).showClusteringWarningMessage === 'function') {
            (window as any).showClusteringWarningMessage(() => enterClusteringMode(urlParams));
        } else {
            enterClusteringMode(urlParams);
        }
    }
}

async function enterClusteringMode(urlParams: URLSearchParams) {
    // If we don't have a config yet, automatically run the default algorithm
    if (!props.currentAlgorithm && selectedAlgorithm.value && props.canCreateClustering) {
        const formData = new FormData();
        formData.append('csrf_token', props.csrfToken);
        formData.append('algorithm', selectedAlgorithm.value);

        try {
            const response = await fetch(props.createClusteringUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.status === 'success') {
                urlParams.set('cluster_mode', '1');
                window.location.search = urlParams.toString();
            } else {
                alert(result.message || 'Error creating clusters');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to connect to the server.');
        }
    } else {
        urlParams.set('cluster_mode', '1');
        window.location.search = urlParams.toString();
    }
}

async function onAlgorithmChange() {
    if (props.isClusteringMode && selectedAlgorithm.value) {
        const formData = new FormData();
        formData.append('csrf_token', props.csrfToken);
        formData.append('algorithm', selectedAlgorithm.value);

        try {
            const response = await fetch(props.createClusteringUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.status === 'success') {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('cluster_mode', '1');
                window.location.search = urlParams.toString();
            } else {
                alert(result.message || 'Error creating clusters');
                // Revert selection if it failed
                selectedAlgorithm.value = props.currentAlgorithm || Object.keys(props.algorithms)[0] || '';
            }
        } catch (error) {
            console.error('Error:', error);
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
      @change="onAlgorithmChange"
      class="form-control"
      style="width: auto;"
    >
      <option
        v-for="(name, id) in algorithms"
        :key="id"
        :value="id"
      >
        {{ name }}
      </option>
    </select>
</template>
