<script setup lang="ts">
/* global WebSocketClient, buildCourseUrl, csrfToken, displaySuccessMessage, displayErrorMessage */
import { ref, onMounted } from 'vue';
import ChatroomRow from '@/components/chat/ChatroomRow.vue';
import ChatroomFormModal from '@/components/chat/ChatroomFormModal.vue';

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
    chatrooms: Chatroom[];
    userAdmin: boolean;
    baseUrl: string;
    csrfToken: string;
}

const props = defineProps<Props>();

const chatroomList = ref<Chatroom[]>([...props.chatrooms]);

// Modal state
const modalVisible = ref(false);
const modalMode = ref<'create' | 'edit'>('create');
const editingChatroom = ref<Chatroom | null>(null);

function openCreateModal() {
    modalMode.value = 'create';
    editingChatroom.value = null;
    modalVisible.value = true;
}

function openEditModal(chatroom: Chatroom) {
    modalMode.value = 'edit';
    editingChatroom.value = chatroom;
    modalVisible.value = true;
}

function closeModal() {
    modalVisible.value = false;
    editingChatroom.value = null;
}

function deleteChatroom(chatroom: Chatroom) {
    if (!confirm(`This will delete chatroom '${chatroom.title}'. Are you sure?`)) {
        return;
    }
    const url = `${props.baseUrl}/delete`;
    const fd = new FormData();
    fd.append('csrf_token', props.csrfToken);
    fd.append('chatroom_id', String(chatroom.id));

    fetch(url, { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                (window as any).displayErrorMessage?.(data.message || 'Something went wrong. Please try again.');
            }
            else {
                window.location.reload();
            }
        })
        .catch(() => {
            window.alert('Something went wrong. Please try again.');
        });
}

function clearChatroom(chatroom: Chatroom) {
    if (!confirm('This will clear all messages in the chatroom. Are you sure?')) {
        return;
    }
    const url = (window as any).buildCourseUrl(['chat', chatroom.id, 'clear']);
    const fd = new FormData();
    fd.append('csrf_token', props.csrfToken);

    fetch(url, { method: 'POST', body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                (window as any).displayErrorMessage?.(data.message || 'Something went wrong. Please try again.');
            }
            else {
                (window as any).displaySuccessMessage?.(`Cleared ${chatroom.title} successfully`);
            }
        })
        .catch(() => {
            window.alert('Something went wrong. Please try again.');
        });
}

// WebSocket integration
function handleChatStateChange(msg: any, isActive: boolean) {
    // Remove existing row if present
    const idx = chatroomList.value.findIndex(c => c.id === msg.chatroom_id);
    if (idx !== -1) {
        chatroomList.value.splice(idx, 1);
    }

    // Add updated/new chatroom
    chatroomList.value.push({
        id: msg.chatroom_id,
        title: msg.title,
        description: msg.description,
        hostName: msg.host_name,
        isAllowAnon: msg.allow_anon,
        isActive: isActive,
        isReadOnly: !isActive && msg.allow_read_only_after_end,
        allowReadOnlyAfterEnd: msg.allow_read_only_after_end,
    });
}

function removeChatroomRow(chatroomId: number) {
    const idx = chatroomList.value.findIndex(c => c.id === chatroomId);
    if (idx !== -1) {
        chatroomList.value.splice(idx, 1);
    }
}

onMounted(() => {
    if (typeof (window as any).WebSocketClient === 'undefined') {
        return;
    }
    const socketClient = new (window as any).WebSocketClient();
    socketClient.onmessage = (msg: any) => {
        const isActive = msg.type === 'chat_open';
        switch (msg.type) {
            case 'chat_open':
            case 'chat_close':
            case 'chat_create':
                handleChatStateChange(msg, isActive);
                break;
            case 'chat_delete':
                removeChatroomRow(msg.chatroom_id);
                break;
            default:
                console.error('Unknown message type:', msg);
        }
    };
    socketClient.open('chatrooms');
});
</script>

<template>
  <div class="content">
    <h1> Live Chat </h1>
    <a
      v-if="userAdmin"
      href="javascript:void(0)"
      data-testid="new-chatroom-btn"
      class="btn btn-primary"
      @click="openCreateModal"
    >New Chatroom</a>
    <hr>
    <div class="chatrooms-table-wrapper table-responsive">
      <h2> Chatrooms </h2>
      <table
        id="chatrooms-table"
        class="table table-striped"
      >
        <!-- Admin View -->
        <template v-if="userAdmin">
          <col style="width: 2.5%">
          <col style="width: 5%">
          <col style="width: 18%">
          <col style="width: 12%">
          <col style="width: 30%">
          <col style="width: 12.5%">
          <col style="width: 15%">
          <col style="width: 10%">
          <thead>
            <tr>
              <th
                scope="col"
                style="text-align: left"
              />
              <th
                scope="col"
                style="text-align: left"
              />
              <th
                scope="col"
                style="text-align: left"
              >
                Name
              </th>
              <th
                scope="col"
                style="text-align: left"
              >
                Status
              </th>
              <th scope="col">
                Description
              </th>
              <th scope="col" />
              <th scope="col" />
              <th scope="col" />
            </tr>
          </thead>
          <tbody data-testid="chatroom-list-item">
            <ChatroomRow
              v-for="chatroom in chatroomList"
              :key="chatroom.id"
              :chatroom="chatroom"
              :is-admin="true"
              :base-url="baseUrl"
              :csrf-token="csrfToken"
              @edit="openEditModal"
              @delete="deleteChatroom"
              @clear="clearChatroom"
            />
          </tbody>
        </template>

        <!-- Student View -->
        <template v-else>
          <col style="width: 22%">
          <col style="width: 12%">
          <col style="width: 22%">
          <col style="width: 22%">
          <col style="width: 22%">
          <thead>
            <tr>
              <th scope="col">
                Name
              </th>
              <th
                scope="col"
                style="text-align: left"
              >
                Status
              </th>
              <th scope="col">
                Host
              </th>
              <th scope="col">
                Description
              </th>
              <th
                scope="col"
                style="text-align: left"
              />
            </tr>
          </thead>
          <tbody data-testid="chatroom-list-item">
            <ChatroomRow
              v-for="chatroom in chatroomList"
              :key="chatroom.id"
              :chatroom="chatroom"
              :is-admin="false"
              :base-url="baseUrl"
              :csrf-token="csrfToken"
            />
          </tbody>
        </template>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <ChatroomFormModal
      :visible="modalVisible"
      :mode="modalMode"
      :base-url="baseUrl"
      :csrf-token="csrfToken"
      :chatroom="editingChatroom"
      @close="closeModal"
    />
  </div>
</template>
