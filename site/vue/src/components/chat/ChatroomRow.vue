<script setup lang="ts">
import { computed } from 'vue';

interface Chatroom {
    id: number;
    title: string;
    description: string;
    hostName: string;
    isAllowAnon: boolean;
    isActive: boolean;
    isReadOnly: boolean;
    allowReadOnlyAfterEnd: boolean;
}

interface Props {
    chatroom: Chatroom;
    isAdmin: boolean;
    baseUrl: string;
    csrfToken: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'edit', chatroom: Chatroom): void;
    (e: 'delete', chatroom: Chatroom): void;
    (e: 'clear', chatroom: Chatroom): void;
}>();

const truncatedTitle = computed(() => {
    return props.chatroom.title.length > 30
        ? `${props.chatroom.title.slice(0, 30)}...`
        : props.chatroom.title;
});

const truncatedDescription = computed(() => {
    return props.chatroom.description.length > 45
        ? `${props.chatroom.description.slice(0, 45)}...`
        : props.chatroom.description;
});

const statusLabel = computed(() => {
    if (!props.chatroom.allowReadOnlyAfterEnd) {
        return null;
    }
    return props.chatroom.isActive ? 'Read-only when closed' : 'Read-only';
});

const joinUrl = computed(() => `${props.baseUrl}/${props.chatroom.id}`);
const anonJoinUrl = computed(() => `${props.baseUrl}/${props.chatroom.id}/anonymous`);
const toggleFormAction = computed(() => `${props.baseUrl}/${props.chatroom.id}/toggleActiveStatus`);

function toggleChatroom() {
    if (props.chatroom.isActive) {
        if (!confirm('This will close the chatroom. Are you sure?')) {
            return;
        }
    }
    const form = document.getElementById(`chatroom_toggle_form_${props.chatroom.id}`) as HTMLFormElement;
    if (form) {
        form.submit();
    }
}

function onDelete() {
    emit('delete', props.chatroom);
}

function onEdit() {
    emit('edit', props.chatroom);
}

function onClear() {
    emit('clear', props.chatroom);
}
</script>

<template>
  <!-- Admin View -->
  <tr
    v-if="isAdmin"
    :id="String(chatroom.id)"
    data-testid="chatroom-item"
  >
    <td>
      <a
        data-testid="edit-chatroom"
        href="javascript:void(0)"
        class="fas fa-pencil-alt black-btn"
        @click="onEdit"
      />
    </td>
    <td>
      <a
        v-if="!chatroom.isActive"
        data-testid="delete-chatroom"
        href="javascript:void(0)"
        class="fas fa-trash-alt black-btn"
        @click="onDelete"
      />
    </td>
    <td class="chatroom-name-cell">
      <span
        data-testid="chatroom-title"
        :title="chatroom.title"
      >
        {{ truncatedTitle }}
      </span>
    </td>
    <td class="chatroom-status-cell">
      <span
        v-if="statusLabel"
        class="badge badge-secondary readonly-badge"
      >
        {{ statusLabel }}
      </span>
    </td>
    <td>
      <span
        data-testid="chatroom-description"
        :title="chatroom.description"
      >
        {{ truncatedDescription }}
      </span>
    </td>
    <td>
      <form
        :id="`chatroom_toggle_form_${chatroom.id}`"
        :action="toggleFormAction"
        method="post"
      >
        <input
          type="hidden"
          name="csrf_token"
          :value="csrfToken"
        >
      </form>
      <button
        v-if="!chatroom.isActive"
        data-testid="enable-chatroom"
        class="btn btn-primary"
        @click="toggleChatroom"
      >
        Start Session
      </button>
      <button
        v-else
        data-testid="disable-chatroom"
        class="btn btn-danger"
        @click="toggleChatroom"
      >
        <i class="fas fa-pause white-icon" />
        End Session
      </button>
    </td>
    <td>
      <a
        :href="joinUrl"
        class="btn btn-primary"
        data-testid="chat-join-btn"
      >Join</a>
      <a
        v-if="chatroom.isAllowAnon"
        data-testid="anon-chat-join-btn"
        :href="anonJoinUrl"
        class="btn btn-default"
      >Join As Anon.</a>
    </td>
    <td>
      <button
        data-testid="clear-chatroom"
        class="btn btn-danger"
        @click="onClear"
      >
        Clear
      </button>
    </td>
  </tr>

  <!-- Student View -->
  <tr
    v-else-if="chatroom.isActive || chatroom.allowReadOnlyAfterEnd"
    :id="String(chatroom.id)"
    data-testid="chatroom-item"
  >
    <td class="chatroom-name-cell">
      <span
        data-testid="chatroom-title"
        class="display-short"
        :title="chatroom.title"
      >
        {{ truncatedTitle }}
      </span>
    </td>
    <td class="chatroom-status-cell">
      <span
        v-if="statusLabel"
        class="badge badge-secondary readonly-badge"
      >
        {{ statusLabel }}
      </span>
    </td>
    <td data-testid="chatroom-host">
      {{ chatroom.hostName }}
    </td>
    <td>
      <span
        data-testid="chatroom-description"
        class="display-short"
        :title="chatroom.description"
      >
        {{ truncatedDescription }}
      </span>
    </td>
    <td>
      <a
        data-testid="chat-join-btn"
        :href="joinUrl"
        class="btn btn-primary"
      >Join</a>
      <template v-if="chatroom.isAllowAnon">
        <i> or </i>
        <a
          data-testid="anon-chat-join-btn"
          :href="anonJoinUrl"
          class="btn btn-default"
        >Join As Anon.</a>
      </template>
    </td>
  </tr>
</template>

<style scoped>
.white-icon {
    color: white;
}
.readonly-badge {
    margin-right: 5px;
}
</style>
