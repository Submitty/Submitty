<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Popup from './Popup.vue';
import type { UnseenNotificationCount } from '@/types/UnseenNotificationCount';
import { buildUrl } from '../../../ts/utils/server';

const visible = ref(false);
const notificationCounts = ref<UnseenNotificationCount[]>([]);

function toggle() {
    visible.value = !visible.value;
}

function getUnseenCounts() {
    $.ajax({
        url: buildUrl(['home', 'get_unseen_counts']),
        type: 'GET',
        dataType: 'json',
        data: { csrf_token: window.csrfToken },
        success(data) {
            notificationCounts.value = data.data;
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
      <div
        v-for="n in notificationCounts"
      >
        <p>{{ n.count }}</p>
      </div>
    </template>
  </Popup>
</template>
