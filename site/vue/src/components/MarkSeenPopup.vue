<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Popup from './Popup.vue';
import type { UnseenNotificationCount } from '@/types/UnseenNotificationCount';
import { buildUrl } from '../../../ts/utils/server';

const visible = ref(false);
const notificationCounts = ref<UnseenNotificationCount[]>([]);
const selected = ref<boolean[]>([]);

function toggle() {
    visible.value = !visible.value;
    selected.value = [];
}

function getUnseenCounts() {
    $.ajax({
        url: buildUrl(['home', 'get_unseen_counts']),
        type: 'GET',
        dataType: 'json',
        data: { csrf_token: window.csrfToken },
        success(data) {
            notificationCounts.value = data.data;
            selected.value = new Array(data.data.length).fill(false);
        },
        error(err) {
            console.error(err);
        }
    });
}
onMounted(() => {
    getUnseenCounts();
});
</script>

<template>
  <Popup
    title="Mark Seen"
    :visible="visible"
    :savable="true"
    @toggle="toggle"
    @save="true"
    dismissText="Cancel"
    saveText="Mark Seen"
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
    <hr/>
    <div
    class="course-count-container"
    v-for="(n, idx) in notificationCounts"
    :key="idx"
    @click="selected[idx] = !selected[idx]"
    >
        <div class="course-count-grid">
            <input
                type="checkbox"
                v-model="selected[idx]"
                @click.stop 
            />
            <span>{{ n.title }}</span>
            <span>{{ n.name }}</span>
            <span><b>({{ n.count }})</b></span>
        </div>
        <hr/>
      </div>
      <div class="select-buttons">
        <a
          class="btn btn-primary"
        >
          Select All
        </a>
        <a
          class="btn btn-primary"
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
.course-count-container:hover {
    background-color: var(--hover-notification);
}
.course-count-grid {
    display: grid;
    grid-template-columns: 1fr 1.25fr 4fr 1fr;
    align-items: center;
    gap: 32px;
    white-space: nowrap;
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
    margin-top: 12px;
}
</style>
