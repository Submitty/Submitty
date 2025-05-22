<script setup lang="ts">
import Cookies from 'js-cookie';
import { onMounted, ref } from 'vue';
import Popup from './popup.vue';

const { columns, labels, cookie } = defineProps<{
    columns: string[];
    labels: string[];
    forced?: string[];
    cookie: string;
}>();

const selected = ref<boolean[]>([]);
const visible = ref(false);

function loadColumns() {
    const cookieData = Cookies.get(cookie)?.split('-') || Array(columns.length).fill('1');
    selected.value = columns.map((_, i) => cookieData[i] === '1');
}
function saveColumns() {
    Cookies.set(
        cookie,
        selected.value.map((v) => (v ? 1 : 0)).join('-'),
        { expires: 365, path: '/' },
    );
    window.location.reload();
}
function fillAll(val: boolean) {
    selected.value = selected.value.map(() => val);
}

function toggle() {
    visible.value = !visible.value;
    if (!visible.value) {
        loadColumns();
    }
}

onMounted(loadColumns);
</script>

<template>
  <Popup
    title="Toggle Columns"
    :visible="visible"
    :savable="true"
    @toggle="toggle"
    @save="saveColumns"
  >
    <template #trigger>
      <div class="btn-wrapper">
        <a
          id="toggle-columns"
          data-testid="toggle-columns"
          class="btn btn-primary"
          @click="toggle"
        >Toggle Columns</a>
      </div>
    </template>
    <template #default>
      <p class="toggle-columns-instructions">
        Select which columns you would like to display.
      </p>
      <div class="toggle-columns-menu">
        <div
          v-for="(id, idx) in columns"
          :key="id"
          class="toggle-checkbox-area"
        >
          <input
            :id="id"
            v-model="selected[idx]"
            type="checkbox"
            class="toggle-columns-box"
            :disabled="forced?.includes(id)"
            :data-testid="id"
          />
          <label :for="id">{{ labels[idx] }}</label>
        </div>
      </div>
      <div class="toggle-all-buttons">
        <a
          class="btn btn-primary"
          @click="fillAll(true)"
        >
          All On
        </a> <a
          class="btn btn-primary"
          @click="fillAll(false)"
        >
          All Off
        </a>
      </div>
    </template>
  </Popup>
</template>
