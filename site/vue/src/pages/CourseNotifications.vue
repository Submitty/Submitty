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
    class="content"
  >
    <NotificationsDisplay :notifications="notifications" />
  </div>
</template>

<style scoped>
</style>