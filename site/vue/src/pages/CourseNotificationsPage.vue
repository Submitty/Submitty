<script setup lang="ts">
import { defineProps } from 'vue';
import NotificationsDisplay from '@/components/NotificationsDisplay.vue';
import type { Notification } from '@/types/Notification';
defineProps<{
    notifications: Notification[];
}>();
</script>

<template>
  <div
    class="content"
  >
    <NotificationsDisplay
      :notifications="notifications"
      :unseenCount="-1"
      :course="true"
    />
  </div>
</template>

<style scoped>

</style>
