<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import type { Notification } from '@/types/Notification';
import { buildUrl } from '../../../ts/utils/server';
import { reactive } from 'vue';

const props = defineProps<{
    notifications: Notification[];
}>();

const localNotifications = reactive([...props.notifications]);

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
        ? localNotifications.filter((n) => !n.seen)
        : localNotifications,
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

function goToCourseNotifications(course: string) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = buildUrl(['home', 'go_to_course_notifications']);
    const courseInput = document.createElement('input');
    courseInput.type = 'hidden';
    courseInput.name = 'course';
    courseInput.value = course;
    form.appendChild(courseInput);
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = window.csrfToken;
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
}

function markSeen(course: string, id: number) {
    $.ajax({
        url: buildUrl(['home', 'mark_seen']),
        type: 'POST',
        data: {
            course: course,
            notification_id: id,
            csrf_token: window.csrfToken,
        },
        success: function () {
            const target = localNotifications.find(
                (n) => n.id === id && n.course === course,
            );
            if (target) {
                target.seen = true;
            }
        },
        error: function (err) {
            console.error(err);
        },
    });
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
          v-if="notifications.length !== 0"
          class="btn btn-default"
          @click="toggleUnseenOnly"
        >
          {{ showUnseenOnly ? 'Show All' : 'Show Unseen Only' }}
        </button>
      </div>
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
      v-for="n in visibleNotifications"
      :key="n.id"
      role="link"
      tabindex="0"
      class="notification"
      :class="{ unseen: !n.seen }"
      @click="goToNotification(n.notification_url)"
    >
      <i
        v-if="n.component === 'forum'"
        class="fas fa-comments notification-type"
        title="Forum"
      />
      <i
        v-else-if="n.component === 'grading'"
        class="fas fa-star notification-type"
        title="Gradeable"
      />
      <i
        v-else-if="n.component === 'team'"
        class="fas fa-users notification-type"
        title="Team Action"
      />

      <div class="notification-content">
        <p class="notification-text">{{ n.content }}</p>
        <div class="notification-time">
          <span
            class="course-notification-link"
            title="Go to notifications"
            @click.stop="goToCourseNotifications(n.course)"
          > {{ n.course }} </span> - {{ n.notify_time }}
        </div>
      </div>
      <a
        v-if="!n.seen"
        class="notification-seen"
        href="#"
        role="button"
        title="Mark as seen"
        aria-label="Mark as seen"
        @click.stop.prevent="markSeen(n.course, Number(n.id))"
        @keydown.enter.stop.prevent="markSeen(n.course, Number(n.id))"
      >
        <i class="far fa-envelope-open notification-seen-icon" />
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

.notification {
  display: flex;
  gap: 6px;
  border-bottom: 1px solid var(--standard-light-gray);
  padding: 9px 0;
  align-items: center;
  padding-left: 20px;
  padding-right: 20px;
  cursor: pointer;
}

.notification:last-of-type {
    border-bottom: none;
}

.notification-text {
  font-weight: 600;
  color: var(--text-black);
  text-decoration: none;
}

.notification a {
  color: var(--text-black);
  text-decoration: none;
}

.notification.unseen {
    background-color: var(--viewed-content);
}

.notification:hover {
    background-color: var(--hover-notification) !important; /* Override seen/unseen bg on hover */
}

.notification a {
  color: var(--text-black);
  text-decoration: none;
}

.course-notification-link {
  cursor: pointer;
}
.course-notification-link:hover {
  text-decoration: underline;
}

.notification.unseen {
    background-color: var(--viewed-content);
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
    all: unset;
    cursor: pointer;
    text-align: center;
    flex: 0 0 auto;
    padding: 10px 16px;
    margin-left: auto;
    color: var(--text-black);
}

.notification-seen:hover {
    border-radius: 1rem;
    background-color: var(--default-white);
}

[data-theme="dark"]
.notification-seen:hover {
    background-color: var(--standard-hover-light-gray);
}

.notification-seen-icon {
  color: var(--text-black) !important; /* Override default style, keep color the same and just update background */
}
</style>
