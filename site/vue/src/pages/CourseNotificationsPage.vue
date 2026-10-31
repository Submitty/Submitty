<script setup lang="ts">
import { computed } from 'vue';
import NotificationsDisplay from '@/components/NotificationsDisplay.vue';
import type { Notification } from '@/types/Notification';

const props = defineProps<{
    notifications: Notification[];
}>();

const emit = defineEmits<{
    'badge-count-change': [count: number];
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
      @badge-count-change="(count: number) => emit('badge-count-change', count)"
    />
  </div>
</template>
