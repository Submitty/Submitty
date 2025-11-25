<script setup lang="ts">
import { ref } from 'vue';
import Popup from './Popup.vue';
import type { UnseenNotificationCount, GetUnseenCountsResponse } from '@/types/UnseenNotificationCount';
import { buildUrl } from '../../../ts/utils/server';

const emit = defineEmits<{
    'mark-all': [payload: { courses: Record<string, unknown>[] }];
}>();
const visible = ref(false);
const notificationCounts = ref<UnseenNotificationCount[]>([]);
const selected = ref<boolean[]>([]);

async function toggle() {
    if (!visible.value) {
        await getUnseenCounts();
    }
    visible.value = !visible.value;
    if (!visible.value) {
        selected.value = [];
    }
}

function getUnseenCounts() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: buildUrl(['home', 'get_unseen_counts']),
            type: 'GET',
            dataType: 'json',
            data: {
                csrf_token: window.csrfToken,
            },
            success(data: GetUnseenCountsResponse) {
                notificationCounts.value = data.data;
                selected.value = new Array<boolean>(data.data.length).fill(false);
                resolve(true);
            },
            error(err) {
                console.error(err);
                reject(new Error('Failed to load unseen counts'));
            },
        });
    });
}

function selectAll() {
    selected.value = new Array<boolean>(notificationCounts.value.length).fill(true);
}

function clearAll() {
    selected.value = new Array<boolean>(notificationCounts.value.length).fill(false);
}

function markSeen() {
    const selectedCourses = notificationCounts.value
        .filter((_, idx) => selected.value[idx])
        .map((item) => ({
            term: item.term,
            course: item.title,
            count: item.count
        }));

    if (selectedCourses.length === 0) {
        void toggle();
        return;
    }

    $.ajax({
        url: buildUrl(['home', 'mark_all_seen']),
        type: 'POST',
        dataType: 'json',
        data: {
            csrf_token: window.csrfToken,
            courses: selectedCourses,
        },
        success() {
            emit('mark-all', { courses: selectedCourses });
            void toggle();
        },
        error(err) {
            console.error('Failed to mark seen:', err);
        },
    });
}

</script>

<template>
  <Popup
    title="Mark Seen"
    :visible="visible"
    :savable="true"
    dismiss-text="Cancel"
    save-text="Mark Seen"
    @toggle="toggle"
    @save="markSeen"
  >
    <template #trigger>
      <button
        class="btn btn-primary"
        @click="toggle"
      >
        Mark as seen
      </button>
    </template>
    <template #default>
      <hr />
      <div
        v-for="(n, idx) in notificationCounts"
        :key="idx"
        class="course-count-container"
        :class="{ 'selected-row': selected[idx] }"
        @click="selected[idx] = !selected[idx]"
      >
        <div class="course-count-grid">
          <input
            v-model="selected[idx]"
            type="checkbox"
            @click.stop
          />
          <span>{{ n.title }}</span>
          <span>{{ n.name }}</span>
          <span><b>{{ n.count }}</b></span>
        </div>
        <hr />
      </div>
      <div class="select-buttons">
        <a
          class="btn btn-primary"
          @click="selectAll"
        >
          Select All
        </a>
        <a
          class="btn btn-primary"
          @click="clearAll"
        >
          Clear Selection
        </a>
      </div>
    </template>
  </Popup>
</template>

<style scoped>
.course-count-container {
    cursor: pointer;
}
.selected-row {
    background-color: var(--viewed-content);
}
.course-count-container:hover {
    background-color: var(--hover-notification);
}
.course-count-grid {
    display: grid;
    grid-template-columns: 1fr 1.25fr 4fr 1fr;
    align-items: center;
    gap: 32px;
    padding: 6px;
}
hr {
    height: 0;
    margin: 0;
}
.select-buttons {
    display: flex;
    flex-direction: row;
    gap: 16px;
    margin-top: 24px;
}
</style>
