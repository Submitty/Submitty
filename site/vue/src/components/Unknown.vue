<script setup lang="ts">
defineProps<{
    type: string;
    name: string;
}>();
</script>

<template>
  <h1>Unknown {{ type }} "{{ name }}"</h1>
</template>

<style scoped>
h1 {
  font-weight: bolder;
}
</style>
