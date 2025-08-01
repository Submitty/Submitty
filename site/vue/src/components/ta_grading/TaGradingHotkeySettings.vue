<script setup lang="ts">
import { type KeymapEntry, remapFinish, updateKeymapAndStorage } from '@/ts/ta-grading-keymap';
import { inject } from 'vue';

const keymap = inject<KeymapEntry<unknown>[]>('keymap', []);
const remapping = inject<{ active: boolean; index: number }>('remapping', { active: false, index: 0 });

// Start remapping
function remapHotkey(index: number) {
    if (remapping.active) {
        return;
    }
    remapping.active = true;
    remapping.index = index;
}

// Reset hotkey
function remapUnset(index: number) {
    remapFinish(keymap, remapping, index, 'Unassigned');
}

// Restore all hotkeys
function restoreAllHotkeys() {
    keymap.forEach((hotkey, index) => {
        updateKeymapAndStorage(keymap, index, hotkey.originalCode || 'Unassigned');
    });
}

// Remove all hotkeys
function removeAllHotkeys() {
    keymap.forEach((_, index) => {
        updateKeymapAndStorage(keymap, index, 'Unassigned');
    });
}
</script>

<template>
  <div class="hotkeys-header">
    <div class="hotkeys-title-buttons">
      <h2>Hotkeys</h2>
      <div class="hotkeys-buttons">
        <button
          id="restore-all-hotkeys"
          class="btn btn-primary hotkeys-button"
          data-testid="restore-all-hotkeys"
          @click="restoreAllHotkeys"
        >
          Restore Default
        </button>
        <button
          id="remove-all-hotkeys"
          class="btn btn-danger hotkeys-button"
          data-testid="remove-all-hotkeys"
          @click="removeAllHotkeys"
        >
          Remove All
        </button>
      </div>
    </div>
  </div>
  <table
    id="hotkeys-list"
    class="ta-grading-setting-list"
  >
    <thead>
      <tr>
        <th>Action</th>
        <th>Hotkey</th>
        <th>Remove</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="(hotkey, index) in keymap"
        :key="index"
      >
        <td>{{ hotkey.name || 'Unassigned' }}</td>
        <td>
          <button
            class="btn remap-button remap-disable"
            :class="[
              hotkey.error ? 'btn-danger' : (hotkey.code === hotkey.originalCode ? 'btn-default' : 'btn-primary')
            ]"
            :data-testid="`remap-${index}`"
            :disabled="remapping.active && remapping.index !== index"
            @click="remapHotkey(index)"
          >
            {{ hotkey.code }}
          </button>
        </td>
        <td
          class="button-cell"
          style="display: flex; justify-content: center; align-items: center;"
        >
          <button
            :data-testid="`remap-unset-${index}`"
            class="btn btn-danger remap-disable"
            :disabled="remapping.active"
            @click="remapUnset(index)"
          >
            &times;
          </button>
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.ta-grading-setting-list {
  width: 100%;
}
.btn {
  margin: 2px;
}
</style>
