<!--
This is the vue component for course notifications. Most of the logic
has conditionals based on the course boolean to determine functionality.
-->

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { Notification } from '@/types/Notification';
import SingleNotification from '@/components/Notification.vue';
import { buildCourseUrl } from '../../../ts/utils/server';

const props = defineProps<{
    notifications: Notification[];
    course: boolean;
    visibleCount: number;
}>();

const localNotifications = ref<Notification[]>([...props.notifications]);

const showUnseenOnly = ref(true);

// Store preference for both home page and course
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

const visibleNotifications = computed(() =>
    props.visibleCount === -1
        ? filteredNotifications.value
        : filteredNotifications.value.slice(0, props.visibleCount),
);

const filteredNotifications = computed(() =>
    showUnseenOnly.value
        ? localNotifications.value.filter((n) => !n.seen)
        : localNotifications.value,
);

function dynamicMarkSeen({ id, course }: { id: number; course: string }) {
    const target = localNotifications.value.find(
        (n) => n.id === id && n.course === course,
    );
    if (target) {
        target.seen = true;
    }
}

// Courses only
function markAllAsSeen() {
    if (props.course) {
        $.ajax({
            url: buildCourseUrl(['notifications', 'seen']),
            type: 'POST',
            data: {
                csrf_token: window.csrfToken,
            },
            success: function () {
                for (const n of localNotifications.value) {
                    if (!n.seen) {
                        n.seen = true;
                    }
                }
            },
            error: function (err) {
                console.error(err);
            },
        });
    }
}
</script>
<template>
  <div>
    <div class="notifications-header-container">
      <h1 class="notifications-header">
        Notifications
      </h1>
      <div class="notifications-actions">
        <button
          v-if="notifications.length !== 0"
          class="btn btn-default"
          @click="toggleUnseenOnly"
        >
          {{ showUnseenOnly ? 'Show All' : 'Show Unseen Only' }}
        </button>
        <button
          v-if="notifications.length !== 0 && course"
          class="btn btn-primary"
          @click="markAllAsSeen"
        >
          Mark as seen
        </button>
        <a
          v-if="course"
          class="btn btn-primary notification-settings-btn"
          :href="buildCourseUrl(['notifications', 'settings'])"
        >
          Settings
        </a>
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
    >
      <SingleNotification
        :notification="n"
        :course="course"
        @dynamic-update="({ id, course }) => dynamicMarkSeen({ id, course })"
      />
    </div>
  </div>
</template>
<style scoped>
.notifications-header-container {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  margin-bottom: 14px;
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

.notification-settings-btn {
  font-family: arial, sans-serif;
}
</style>
