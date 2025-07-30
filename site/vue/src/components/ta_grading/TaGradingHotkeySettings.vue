<script setup lang="ts">
function restoreAllHotkeys() {
    // something
}
function removeAllHotkeys() {
    // something
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
  <div id="hotkeys-list" />
</template>
