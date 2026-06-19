<script setup lang="ts">
import { ref } from 'vue';

const main = () => document.querySelector('main#main');
const isFullScreen = ref(main()?.classList.contains('full-screen-mode') ?? false);

function toggle() {
    const el = main();
    if (!el) {
        return;
    }
    el.classList.toggle('full-screen-mode');
    isFullScreen.value = el.classList.contains('full-screen-mode');
}
</script>

<template>
  <button 
          id="fullscreen-btn" 
          data-testid="fullscreen-btn" 
          class="btn btn-default" 
          title="Toggle full screen mode" 
          @click="toggle">
    <i :class="`fas ${isFullScreen ? 'fa-compress' : 'fa-expand'}`" />
  </button>
</template>

<style scoped>
.fullscreen-btn-cont {
    display: inline-block;
    position: relative;
    margin-right: 4px;
    border: 2px solid var(--text-black);
    border-radius: 5px;
    box-shadow: 1px 1px 2px var(--text-black);
}
.fullscreen-btn-cont button {
    cursor: pointer;
}
.fullscreen-icon {
    font-size: 27px;
    padding: 3px 8px;
    margin: 1px;
    color: var(--text-black);
}
</style>
