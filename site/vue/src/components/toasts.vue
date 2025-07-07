<script setup lang="ts">
import { defineProps, ref, computed, onMounted, type Ref } from 'vue';

type Message = {
    type: 'error' | 'success' | 'warning';
    key: number;
    message: string;
    expiresAt?: number; // Optional expiration time in milliseconds
};

const props = defineProps<{
    messages: Message[];
}>();

// Single array to manage all messages
const allMessages: Ref<Message[]> = ref([]);

// Initialize with props messages
onMounted(() => {
    // Convert incoming messages to our format with expiration
    allMessages.value = props.messages.map((msg) => ({
        ...msg,
        expiresAt: msg.type === 'success' ? Date.now() + 5000 : undefined,
    }));
    cleanupExpiredMessages(); // Initial cleanup on mount
});

// Computed property to display only active messages
const activeMessages = computed(() => {
    const now = Date.now();
    // Filter out expired messages
    return allMessages.value.filter((m) => !m.expiresAt || m.expiresAt > now);
});

function removeMessagePopup(key: number) {
    allMessages.value = allMessages.value.filter((m) => m.key !== key);
}

function cleanupExpiredMessages() {
    const now = Date.now();
    allMessages.value = allMessages.value.filter((m) => !m.expiresAt || m.expiresAt > now);
    const nextCleanup = allMessages.value
        .filter((m) => m.expiresAt)
        .map((m) => m.expiresAt!)
        .reduce((min, curr) => Math.min(min, curr), Infinity);
    // Set a timeout for the next cleanup if there are any messages with expiration
    if (nextCleanup !== Infinity) {
        setTimeout(() => {
            cleanupExpiredMessages();
        }, nextCleanup - Date.now() + 100); // Add a small buffer
    }
}

function displayMessage(message: string, type: 'error' | 'success' | 'warning') {
    const key = Date.now(); // Use current timestamp as a unique key
    const expiresAt = type === 'error' ? undefined : Date.now() + 5000; // Set expiration for non-error messages

    allMessages.value.push({
        type,
        key,
        message,
        expiresAt,
    });

    // Set a timeout to clean up expired messages
    if (expiresAt) {
        setTimeout(() => {
            cleanupExpiredMessages();
        }, expiresAt - Date.now() + 100); // Add a small buffer
    }
}

// Expose methods to window
window.removeMessagePopup = removeMessagePopup;
window.displayMessage = displayMessage;
window.displayErrorMessage = (message: string) => displayMessage(message, 'error');
window.displaySuccessMessage = (message: string) => displayMessage(message, 'success');
window.displayWarningMessage = (message: string) => displayMessage(message, 'warning');
</script>

<template>
  <div id="messages">
    <div
      v-for="message in activeMessages"
      :id="`${message.type}-${message.key}`"
      :key="message.key"
      :class="`inner-message alert alert-${message.type}`"
      data-testid="popup-message"
    >
      <span>
        <i
          v-if="message.type === 'error'"
          class="fas fa-times-circle"
        />
        <i
          v-else-if="message.type === 'success'"
          class="fas fa-check-circle"
        />
        <i
          v-else
          class="fas fa-circle-exclamation"
        />{{ message.message }}
      </span>
      <a
        id="remove_popup"
        class="fas fa-times key_to_click"
        tabindex="0"
        @click="removeMessagePopup(message.key)"
      />
    </div>
  </div>
</template>

<style scoped>
#messages {
  z-index: 999;
  position: fixed;
  top: 40px;
  left: 50%;
  width: 30%;
  min-width: 450px;
  transform: translateX(-50%);
}

.inner-message {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 0;
  /* stylelint-disable-next-line declaration-no-important */
  padding: 8px 14px !important;
}
</style>
