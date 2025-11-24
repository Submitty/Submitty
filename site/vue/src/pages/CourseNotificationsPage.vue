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
      :course="true"
      :visible-count="-1"
    />
  </div>
</template>

<style scoped>

</style>
