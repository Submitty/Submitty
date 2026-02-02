<script setup lang="ts">
import type { Notification } from '@/types/Notification';
import { buildUrl, buildCourseUrl } from '../../../ts/utils/server';

const props = defineProps<{
    notification: Notification;
    course: boolean;
}>();

const emit = defineEmits<{
    'mark-individual': [payload: { id: number; course: string }];
}>();

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
    // Course Page
    if (props.course) {
        $.ajax({
            url: buildCourseUrl(['notifications', 'mark_seen']),
            type: 'POST',
            data: {
                course: course,
                notification_id: id,
                csrf_token: window.csrfToken,
            },
            success: function () {
                emit('mark-individual', { id, course });
            },
            error: function (err) {
                console.error(err);
            },
        });
    }
    // Home Page
    else {
        $.ajax({
            url: buildUrl(['home', 'mark_seen']),
            type: 'POST',
            data: {
                course: course,
                notification_id: id,
                csrf_token: window.csrfToken,
            },
            success: function () {
                emit('mark-individual', { id, course });
            },
            error: function (err) {
                console.error(err);
            },
        });
    }
}
</script>
<template>
  <div
    :key="notification.id"
    tabindex="0"
    class="notification"
    :class="{ unseen: !notification.seen }"
  >
    <a
      class="main-notification-content"
      :href="props.notification.url"
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
            v-if="!course"
          >
            <span
              class="course-notification-link"
              title="Go to notifications"
              @click.stop="goToCourseNotifications(notification.course)"
            >
              {{ notification.course_name ? notification.course_name : notification.course }}
            </span>
            -
          </span>
          {{ notification.notify_time }}
        </div>
      </div>
    </a>
    <button
      v-if="!notification.seen"
      class="notification-seen"
      title="Mark as seen"
      aria-label="Mark as seen"
      @click.stop.prevent="markSeen(notification.course, Number(notification.id))"
      @keydown.enter.stop.prevent="markSeen(notification.course, Number(notification.id))"
    >
      <i class="far fa-envelope-open notification-seen-icon" />
    </button>
  </div>
</template>
<style scoped>
.notification {
  display: flex;
  flex-direction: row;
  border-bottom: 1px solid var(--standard-light-gray);
  align-items: center;
}

.notification a {
  text-decoration: none;
}

.main-notification-content {
  flex-grow: 1;
  display: flex;
  flex-direction: row;
  align-items: center;
  padding: 10px 10px 10px 20px;
  cursor: pointer;
}

.notification-content {
    flex: 1;
}

.notification-text {
  font-weight: 600;
  color: var(--text-black);
  text-decoration: none;
}

.notification.unseen {
    background-color: var(--viewed-content);
}

.notification:hover {
    background-color: var(--hover-notification) !important; /* Override seen/unseen bg on hover */
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

.notification-type {
    margin-right: 1em;
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
    padding: 13px 18px;
    margin-right: 5px;
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
