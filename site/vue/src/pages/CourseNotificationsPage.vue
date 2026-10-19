<script setup lang="ts">
import NotificationsDisplay from '@/components/NotificationsDisplay.vue';
import type { Notification } from '@/types/Notification';

defineProps<{
    notifications: Notification[];
}>();

const emit = defineEmits<{
    'update-badge-dom': [count: number];
}>();
</script>

<template>
  <div
    class="content"
  >
    <NotificationsDisplay
      :notifications="notifications"
      :unseen-count="notifications.filter((n) => !n.seen).length"
      :course="true"
      @update-badge-dom="(count: number) => emit('update-badge-dom', count)"
    />
  </div>
</template>
