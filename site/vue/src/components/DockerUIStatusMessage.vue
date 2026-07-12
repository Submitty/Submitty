<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';

enum statusTypes {
    UpdateRequired = 'Update Required',
    ChangesPending = 'Changes Pending',
    Reload = 'Up-to-Date (Reload)',
    UpToDate = 'Up-to-Date',
};

const status = ref(sessionStorage.getItem('docker-ui-status-message'));

onMounted(() => {
    let classList = '';
    switch (props.status) {
        case statusTypes.UpdateRequired: {
            classList += 'btn-danger';
            break;
        }
        case statusTypes.ChangesPending: {
            classList += 'btn-warning';
            break;
        }
        case statusTypes.UpToDate:
        default: {
            classList += 'btn-success';
            break;
        }
}
});


</script>

<template>
  <span
    :class="`btn status btn-${message.type}`"
    data-testid="docker-status"
    style=""
  >Changes Pending
  </span>
</template>

<style scoped>
span {
  margin-top: 1em
}
</style>
