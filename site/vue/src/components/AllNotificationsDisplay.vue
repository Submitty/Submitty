<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { Notification } from '@/types/Notification';
import SingleNotification from '@/components/Notification.vue';

const props = defineProps<{
    notifications: Notification[];
}>();

const localNotifications = ref<Notification[]>([...props.notifications]);

const showUnseenOnly = ref(true);

onMounted(() => {
    const pref = localStorage.getItem('notification-preference');
    if (pref === 'unseen') {
        showUnseenOnly.value = true;
    }
    else if (pref === 'all') {
        showUnseenOnly.value = false;
    }
});

function toggleUnseenOnly() {
    showUnseenOnly.value = !showUnseenOnly.value;
    localStorage.setItem(
        'notification-preference',
        showUnseenOnly.value ? 'unseen' : 'all',
    );
}

const visibleCount = 10;

const filteredNotifications = computed(() =>
    showUnseenOnly.value
        ? localNotifications.value.filter((n) => !n.seen)
        : localNotifications.value,
);

const visibleNotifications = computed(() =>
    filteredNotifications.value.slice(0, visibleCount),
);

function goToNotification(url: string) {
    if (!url) {
        return;
    }
    window.location.href = url;
}

function dynamicMarkSeen({ id, course }: { id: number; course: string }) {
    const target = localNotifications.value.find(
        (n) => n.id === id && n.course === course,
    );
    if (target) {
        target.seen = true;
    }
}
</script>
<template>
  <div class="notification-panel shadow">
    <div class="notifications-header-container">
      <h1 class="notifications-header">
        Notifications
      </h1>
      <div class="notifications-actions">
        <button
          v-if="localNotifications.length !== 0"
          class="btn btn-default"
          @click="toggleUnseenOnly"
        >
          {{ showUnseenOnly ? 'Show All' : 'Show Unseen Only' }}
        </button>
      </div>
    </div>
    <p
      v-if="localNotifications.length === 0"
      id="no-recent-notifications"
      class="no-recent"
    >
      No notifications to view.
    </p>
    <p
      v-if="filteredNotifications.length === 0 && localNotifications.length > 0"
      id="no-recent-notifications"
      class="no-recent"
    >
      No unseen notifications.
    </p>
    <div
      v-for="n in visibleNotifications"
      :key="n.id"
      role="link"
      tabindex="0"
      class="notification"
      :class="{ unseen: !n.seen }"
      @click="goToNotification(n.notification_url)"
    >
      <SingleNotification
        :notification="n"
        @dynamic-update="({ id, course }) => dynamicMarkSeen({ id, course })"
      />
    </div>
  </div>
</template>
<style scoped>
.notification-panel {
    background-color: var(--default-white);
    height: auto;
    padding: 20px
}

.notifications-header-container {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  margin-bottom: 10px;
}

.notifications-header {
    flex-grow: 1;
}

.no-recent {
    padding-top: 10px;
    font-style: italic;
    margin-bottom: 30px;
}

#notifications {
    display: table;
    width: 100%;
    border-collapse: collapse;
}

.notifications-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}
</style>
