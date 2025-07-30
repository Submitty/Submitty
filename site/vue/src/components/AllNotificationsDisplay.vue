<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

interface Notification {
    id: number;
    component: string;
    metadata: string;
    content: string;
    seen: boolean;
    elapsed_time: number;
    created_at: string;
    notify_time: string;
    semester: string;
    course: string;
    notification_url: string;
}

const props = defineProps<{
    notifications: Notification[];
}>();

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
        ? props.notifications.filter((n) => !n.seen)
        : props.notifications,
);

const visibleNotifications = computed(() =>
    filteredNotifications.value.slice(0, visibleCount),
);
</script>
<template>
  <div class="notification-panel shadow">
    <div class="notifications-header-container">
      <h1 class="notifications-header">
        Notifications
      </h1>
      <button
        v-if="notifications.length !== 0"
        class="btn btn-default"
        @click="toggleUnseenOnly"
      >
        {{ showUnseenOnly ? 'Show All' : 'Show Unseen Only' }}
      </button>
      <!-- FUTURE FEATURE: mark all notifications on the home page as seen
            <a class="btn btn-primary">
                Mark all as seen
            </a>
        -->
    </div>
    <p
      v-if="notifications.length === 0"
      id="no-recent-notifications"
      class="no-recent"
    >
      No notifications to view.
    </p>
    <p
      v-if="filteredNotifications.length === 0 && notifications.length > 0"
      id="no-recent-notifications"
      class="no-recent"
    >
      No unseen notifications.
    </p>
    <div
      v-else
      id="recent-notifications"
    >
      <a
        v-for="n in visibleNotifications"
        :key="n.id"
        class="notification"
        :class="{ unseen: !n.seen }"
        :href="n.notification_url"
      >
        <i
          v-if="n.component === 'forum'"
          class="fas fa-comments notification-type"
          title="Forum"
        />
        <div class="notification-content">
          <span>
            {{ n.content }}
          </span>
          <div class="notification-time">
            {{ n.course }} - {{ n.notify_time }}
          </div>
        </div>
        <!-- FUTURE FEATURE: individual mark as seen
                      <a class="notification-seen black-btn" title="Mark as seen" aria-label="Mark as seen" v-if="!n.seen">
                          <i class="far fa-envelope-open"></i>
                      </a>
                  -->
      </a>
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
  margin-bottom: 5px;
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

.notification {
    display: flex;
    border-bottom: 1px solid var(--standard-light-gray);
    padding: 9px 0;
    align-items: center;
    padding-left: 20px;
    padding-right: 20px;
}

.notification:hover {
    cursor: pointer;
    background-color: var(--hover-notification) !important; /* Override seen/unseen bg on hover */
}

.notification.unseen {
    background-color: var(--viewed-content);
}

a.notification {
    color: var(--text-black);
    text-decoration: none;
}

a.notification:last-of-type {
    border-bottom: none;
}

.notification > * {
    display: block;
    align-items: center;
}

.notification-type {
    margin-right: 1em;
}

.notification-contents {
    display: flex;
    flex-wrap: wrap;
    flex-grow: 1;
}

.notification-content {
    flex: 1;
}

.notification-time {
    font-size: 0.9rem;
    color: var(--standard-medium-dark-gray);
    flex: 1 0 90%;
    margin-top: 2px;
}

.notification-seen {
    text-align: center;
    flex: 0 0 auto;
    padding: 10px 16px;
}

a.show-more {
    display: inline-block;
    margin-top: 10px;
}
</style>
