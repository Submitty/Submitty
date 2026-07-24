<script setup lang="ts">
import { watch } from 'vue';

const enum statusType {
    Danger: 'danger'
}

let status_message: string = '';
let status_type:

watch(
    () => sessionStorage.getItem('docker-ui-status-message'),
    (new_value) => {
        status_message = new_value || '';
    },
    { immediate: true }
)

// onMounted(() => {
//     let classList = '';
//     switch (props.status) {
//         case statusTypes.UpdateRequired: {
//             classList += 'btn-danger';
//             break;
//         }
//         case statusTypes.ChangesPending: {
//             classList += 'btn-warning';
//             break;
//         }
//         case statusTypes.UpToDate:
//         default: {
//             classList += 'btn-success';
//             break;
//         }
// }
// });


</script>

<template>
  <span
    :class="`btn status btn-${message.type}`"
    data-testid="docker-status"
    style=""
  >
    {{ status_message }}
  </span>
</template>

<style scoped>
span {
  margin-top: 1em
}
</style>
