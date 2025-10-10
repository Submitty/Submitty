<script setup lang="ts">
import type { Notification } from '@/types/Notification';
import { buildUrl } from '../../../ts/utils/server';

defineProps<{ notification: Notification }>();

const emit = defineEmits<{
    'dynamic-update': [payload: { id: number; course: string }];
}>();

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
            emit('dynamic-update', { id, course });
        },
        error: function (err) {
            console.error(err);
        },
    });
}
</script>
<template>
  <div
    :key="notification.id"
    role="link"
    tabindex="0"
    class="notification"
    :class="{ unseen: !notification.seen }"
    @click="goToNotification(notification.notification_url)"
  >
    <i
      v-if="notification.component === 'forum'"
      class="fas fa-comments notification-type"
      title="Forum"
    />
    <i
      v-else-if="notification.component === 'grading'"
      class="fas fa-star notification-type"
      title="Gradeable"
    />
    <i
      v-else-if="notification.component === 'team'"
      class="fas fa-users notification-type"
      title="Team Action"
    />

    <div class="notification-content">
      <p class="notification-text">
        {{ notification.content }}
      </p>
      <div class="notification-time">
        <span
          class="course-notification-link"
          title="Go to notifications"
          @click.stop="goToCourseNotifications(notification.course)"
        > {{ notification.course }} </span> - {{ notification.notify_time }}
      </div>
    </div>
    <a
      v-if="!notification.seen"
      class="notification-seen"
      href="#"
      role="button"
      title="Mark as seen"
      aria-label="Mark as seen"
      @click.stop.prevent="markSeen(notification.course, Number(notification.id))"
      @keydown.enter.stop.prevent="markSeen(notification.course, Number(notification.id))"
    >
      <i class="far fa-envelope-open notification-seen-icon" />
    </a>
  </div>
</template>
<style scoped>
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
