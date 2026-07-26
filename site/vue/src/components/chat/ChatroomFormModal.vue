<script setup lang="ts">
import { ref, watch } from 'vue';
import Popup from '../Popup.vue';

interface Chatroom {
    title: string;
    description: string;
    isAllowAnon: boolean;
    allowReadOnlyAfterEnd: boolean;
}

interface Props {
    visible: boolean;
    mode: 'create' | 'edit';
    chatroom?: Chatroom | null;
}

const props = withDefaults(defineProps<Props>(), {
    chatroom: null,
});

const emit = defineEmits<{
    close: [];
    save: [data: { title: string; description: string; allowAnon: boolean; allowReadOnlyAfterEnd: boolean }];
}>();

const title = ref('');
const description = ref('');
const allowAnon = ref(true);
const allowReadOnlyAfterEnd = ref(false);

watch(() => props.visible, (visible) => {
    if (visible) {
        if (props.mode === 'edit' && props.chatroom) {
            title.value = props.chatroom.title;
            description.value = props.chatroom.description;
            allowAnon.value = props.chatroom.isAllowAnon;
            allowReadOnlyAfterEnd.value = props.chatroom.allowReadOnlyAfterEnd;
        }
        else {
            title.value = '';
            description.value = '';
            allowAnon.value = true;
            allowReadOnlyAfterEnd.value = false;
        }
    }
}, { immediate: true });

function submitForm() {
    emit('save', {
        title: title.value,
        description: description.value,
        allowAnon: allowAnon.value,
        allowReadOnlyAfterEnd: allowReadOnlyAfterEnd.value,
    });
}
</script>

<template>
  <Popup
    :id="mode === 'create' ? 'create-chatroom-form' : 'edit-chatroom-form'"
    :title="mode === 'create' ? 'Create Chatroom' : 'Edit Chatroom'"
    :visible="visible"
    savable
    save-text="Submit"
    @toggle="$emit('close')"
    @save="submitForm"
  >
    <template #trigger>
      <span class="hidden-trigger" />
    </template>
    <div class="flex-col flex-col-space">
      <label for="chatroom-modal-title">
        Chatroom Title:
      </label>
      <input
        id="chatroom-modal-title"
        v-model="title"
        type="text"
        name="title"
        :data-testid="mode === 'create' ? 'chatroom-name-entry' : 'chatroom-name-edit'"
        placeholder="Enter name here..."
      >
      <label for="chatroom-modal-description">
        Description:
      </label>
      <input
        id="chatroom-modal-description"
        v-model="description"
        type="text"
        name="description"
        :data-testid="mode === 'create' ? 'chatroom-description-entry' : 'chatroom-description-edit'"
        placeholder="Enter description here..."
      >
      <label
        id="allow-anon-label"
        for="chatroom-modal-allow-anon"
      >
        Allow people to join anonymously?
        <input
          id="chatroom-modal-allow-anon"
          v-model="allowAnon"
          type="checkbox"
          name="allow-anon"
          :data-testid="mode === 'create' ? 'enable-disable-anon' : 'edit-anon'"
        >
      </label>
      <label
        id="allow-read-only-label"
        for="chatroom-modal-read-only"
      >
        Enable read-only after session ends?
        <input
          id="chatroom-modal-read-only"
          v-model="allowReadOnlyAfterEnd"
          type="checkbox"
          name="allow_read_only_after_end"
          :data-testid="mode === 'create' ? 'edit-read-only' : 'edit-read-only'"
        >
      </label>
    </div>
  </Popup>
</template>

<style scoped>
.hidden-trigger {
    display: none;
}
</style>
