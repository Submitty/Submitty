<script setup lang="ts">
<<<<<<< HEAD
=======

>>>>>>> 478d28dbc (Fix #11535: Suppress abort alerts during Firefox page reloads)
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
      :unseen-count="-1"
      :course="true"
    />
  </div>
</template>

<style scoped>

</style>
