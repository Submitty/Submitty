<script setup lang="ts">
import { computed } from 'vue';
import NotificationsDisplay from '@/components/NotificationsDisplay.vue';
import type { Notification } from '@/types/Notification';

const props = defineProps<{
    notifications: Notification[];
}>();

const emit = defineEmits<{
    'update-badge-dom': [count: number];
}>();

const unseenCount = computed(() => props.notifications.filter((n) => !n.seen).length);
</script>

<template>
  <div
    class="content"
    data-testid="course-notifications-page"
  >
    <NotificationsDisplay
      :notifications="notifications"
      :unseen-count="unseenCount"
      :course="true"
      @update-badge-dom="(count: number) => emit('update-badge-dom', count)"
    />
  </div>
</template>
