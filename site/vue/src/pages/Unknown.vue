<script setup lang="ts">
defineProps<{
    page: string;
}>();
</script>

<template>
  <h1>Unknown page "{{ page }}"</h1>
</template>

<style scoped>
h1 {
  font-weight: bolder;
}
</style>
