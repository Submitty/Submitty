<!--
This is the vue component for course notifications. Most of the logic
has conditionals based on the course boolean to determine functionality.
-->

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { Notification } from '@/types/Notification';
import SingleNotification from '@/components/Notification.vue';
import { buildCourseUrl } from '../../../ts/utils/server';
import MarkSeenPopup from './MarkSeenPopup.vue';

const props = defineProps<{
    notifications: Notification[];
    unseenCount: number;
    course: boolean;
}>();

const showPopup = ref(false);
const showUnseenOnly = ref(true);
const localUnseenCount = ref(props.unseenCount);

// Preference is the same between course and home pages
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

// All notifications that are sent from the backend
const localNotifications = ref<Notification[]>([...props.notifications]);

// Filter between most recent notifications and most recent unseen notifications based on local storage
const filteredNotifications = computed(() =>
    showUnseenOnly.value
        ? localNotifications.value.filter((n) => !n.seen)
        : localNotifications.value,
);

// Manage maxumum number of displayed notifications based on course or home page
const visibleNotifications = computed(() =>
    props.course
        ? filteredNotifications.value
        : filteredNotifications.value.slice(0, 10),
);

function markSeen() {
    // Course Page
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
    // Home Page
    else {
        showPopup.value = true;
    }
}

// mark notification as seen without reloading
function markIndividualSeen({ id, course }: { id: number; course: string }) {
    for (const n of localNotifications.value) {
        if (n.id === id && n.course === course) {
            n.seen = true;
            localUnseenCount.value--;
            break;
        }
    }
}

// mark specified course notifications as seen without reloading
function markAllSeen(courses: Record<string, unknown>[]) {
    for (const { term, course, count } of courses) {
        localUnseenCount.value = localUnseenCount.value - Number(count);
        for (const n of localNotifications.value) {
            if (n.term === term && n.course === course) {
                n.seen = true;
            }
        }
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
          v-if="notifications.length !== 0 && props.course"
          class="btn btn-primary"
          @click="markSeen"
        >
          Mark as seen
        </button>
        <MarkSeenPopup
          v-if="notifications.length !== 0 && !props.course"
          @mark-all="({ courses }) => markAllSeen(courses)"
        />
        <a
          v-if="props.course"
          class="btn btn-primary notification-settings-btn"
          :href="buildCourseUrl(['notifications', 'settings'])"
          data-testid="notification-settings-button"
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
      v-if="visibleNotifications.length === 0 && localNotifications.length > 0 && localUnseenCount <= 0"
      id="no-recent-notifications"
      class="no-recent"
    >
      No unseen notifications.
    </p>
    <div
      v-for="n in visibleNotifications"
      :key="n.id"
      class="notification"
      :class="{ unseen: !n.seen }"
    >
      <SingleNotification
        :notification="n"
        :course="props.course"
        @mark-individual="({ id, course }) => markIndividualSeen({ id, course })"
      />
    </div>
    <div
      v-if="!props.course && showUnseenOnly || (visibleNotifications.length === 0 && localNotifications.length > 0) && localUnseenCount > 0"
    >
      <!-- Additional notifications in the front-end -->
      <p
        v-if="visibleNotifications.length >= 10 && localUnseenCount >= 11"
        class="unseen-count-p"
      >
        You have <span class="unseen-count">{{ localUnseenCount - 10 }}</span> additional unseen notification<span v-if="localUnseenCount > 11">s</span>.
      </p>
      <!-- Unseen notifications that will not reach the front-end -->
      <p
        v-if="visibleNotifications.length < 10 && localUnseenCount > 0 && localNotifications.length > 10 && (localUnseenCount - visibleNotifications.length) > 0"
        class="unseen-count-p"
      >
        You have <span class="unseen-count">{{ localUnseenCount - visibleNotifications.length }}</span> older unseen notification<span v-if="localUnseenCount > 1">s</span> in your course notifications not displayed here.
      </p>
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
.unseen-count-p {
  padding-top: 10px;
  font-weight: 600;
}
.unseen-count {
  color: var(--badge-backgroud-red);
  font-weight: 900;
}
</style>
