<script setup lang="ts">
defineProps<{
    component: string;
}>();
</script>

<template>
  <h1>Unknown component "{{ component }}"</h1>
</template>

<style scoped>
h1 {
  font-weight: bolder;
}
</style>
