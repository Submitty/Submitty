<script setup lang="ts">
import { defineProps } from 'vue';
import { buildUrl } from '../../../ts/utils/server';
import NotificationsDisplay from '@/components/NotificationsDisplay.vue';
import type { Notification } from '@/types/Notification';
interface Props {
    notifications: Notification[];
}
const props = defineProps<Props>();
</script>

<template>
  <div
    class="notifications-content"
  >
    <NotificationsDisplay
        :notifications="notifications"
        :course=true
    />
  </div>
</template>

<style scoped>
.notifications-content {
    margin: 10px;
    padding: 10px;
}
</style>