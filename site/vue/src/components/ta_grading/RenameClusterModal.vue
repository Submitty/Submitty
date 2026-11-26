<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';

const props = defineProps<{
    clusterId: number;
    clusterName: string;
    renameUrl: string;
    csrfToken: string;
}>();

const emit = defineEmits<{
    renamed: [];
}>();

const showModal = ref(false);
const clusterName = ref(props.clusterName);
const saving = ref(false);
const error = ref('');
const nameInput = ref<HTMLInputElement | null>(null);
const renameButton = ref<HTMLButtonElement | null>(null);
const canSave = computed(() => !saving.value && clusterName.value.trim() !== '' && clusterName.value.trim() !== props.clusterName);

async function openModal() {
    clusterName.value = props.clusterName;
    error.value = '';
    showModal.value = true;
    await nextTick();
    nameInput.value?.focus();
    nameInput.value?.select();
}

function closeModal() {
    if (saving.value) {
        return;
    }
    showModal.value = false;
    renameButton.value?.focus();
}

async function submitRename() {
    if (!canSave.value) {
        return;
    }

    saving.value = true;
    error.value = '';
    const formData = new FormData();
    formData.append('csrf_token', props.csrfToken);
    formData.append('cluster_name', clusterName.value.trim());

    try {
        const response = await fetch(props.renameUrl, {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' },
        });
        const result = (await response.json()) as { status: string; message?: string };
        if (!response.ok || result.status !== 'success') {
            error.value = result.message || 'Failed to rename cluster.';
            return;
        }

        showModal.value = false;
        renameButton.value?.focus();
        emit('renamed');
    }
    catch (e) {
        console.error('Error renaming cluster:', e);
        error.value = 'Failed to connect to the server.';
    }
    finally {
        saving.value = false;
    }
}
</script>

<template>
  <button
    ref="renameButton"
    type="button"
    class="btn btn-link btn-xs rename-cluster-btn"
    title="Rename Cluster"
    aria-label="Rename Cluster"
    data-testid="rename-cluster-btn"
    @click.stop="openModal"
  >
    <i
      class="fas fa-pencil-alt"
      aria-hidden="true"
    />
  </button>

  <Teleport to="body">
    <div
      v-if="showModal"
      class="popup-form"
      style="display: block;"
      @keydown.esc="closeModal"
    >
      <div
        class="popup-box"
        @click.self="closeModal"
      >
        <div
          class="popup-window"
          style="width: 400px; max-width: calc(100vw - 32px); margin: auto;"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="`rename-cluster-title-${clusterId}`"
        >
          <div class="form-title">
            <h1 :id="`rename-cluster-title-${clusterId}`">
              Rename Cluster
            </h1>
            <button
              type="button"
              class="btn btn-default close-button"
              :disabled="saving"
              @click="closeModal"
            >
              Close
            </button>
          </div>
          <form
            class="form-body"
            @submit.prevent="submitRename"
          >
            <label :for="`cluster-name-${clusterId}`">Cluster name</label>
            <input
              :id="`cluster-name-${clusterId}`"
              ref="nameInput"
              v-model="clusterName"
              type="text"
              class="form-control"
              maxlength="255"
              required
              :disabled="saving"
              :aria-invalid="error ? 'true' : undefined"
              :aria-describedby="error ? `rename-cluster-error-${clusterId}` : undefined"
              data-testid="cluster-name-input"
            >
            <p
              v-if="error"
              :id="`rename-cluster-error-${clusterId}`"
              role="alert"
            >
              {{ error }}
            </p>
            <div class="form-buttons">
              <div
                class="form-button-container"
                style="justify-content: flex-end; display: flex; gap: 10px;"
              >
                <button
                  type="button"
                  class="btn btn-default close-button"
                  :disabled="saving"
                  @click="closeModal"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="!canSave"
                  data-testid="save-cluster-name-btn"
                >
                  {{ saving ? 'Saving...' : 'Save' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </Teleport>
</template>
