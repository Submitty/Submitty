<script setup lang="ts">
import { ref, onMounted } from 'vue';
import ChatroomRow from '@/components/chat/ChatroomRow.vue';
import ChatroomFormModal from '@/components/chat/ChatroomFormModal.vue';

interface ChatroomMessage {
    chatroom_id: number;
    title: string;
    description: string;
    host_name: string;
    allow_anon: boolean;
    allow_read_only_after_end: boolean;
    type: 'chat_open' | 'chat_close' | 'chat_create' | 'chat_delete';
}

interface WebSocketClient {
    onmessage: (msg: ChatroomMessage) => void;
    open: (channel: string) => void;
}

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
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'delete-chatroom': [chatroom: Chatroom];
    'clear-chatroom': [chatroom: Chatroom];
    'toggle-chatroom': [chatroom: Chatroom];
    'create-chatroom': [data: { title: string; description: string; allowAnon: boolean; allowReadOnlyAfterEnd: boolean }];
    'edit-chatroom': [data: { id: number; title: string; description: string; allowAnon: boolean; allowReadOnlyAfterEnd: boolean }];
}>();

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

function onDeleteChatroom(chatroom: Chatroom) {
    emit('delete-chatroom', chatroom);
}

function onClearChatroom(chatroom: Chatroom) {
    emit('clear-chatroom', chatroom);
}

function onToggleChatroom(chatroom: Chatroom) {
    emit('toggle-chatroom', chatroom);
}

function onSaveForm(data: { title: string; description: string; allowAnon: boolean; allowReadOnlyAfterEnd: boolean }) {
    if (modalMode.value === 'create') {
        emit('create-chatroom', data);
    }
    else if (editingChatroom.value) {
        emit('edit-chatroom', {
            id: editingChatroom.value.id,
            ...data,
        });
    }
    closeModal();
}

// WebSocket integration
function handleChatStateChange(msg: ChatroomMessage, isActive: boolean) {
    // Remove existing row if present
    const idx = chatroomList.value.findIndex((c) => c.id === msg.chatroom_id);
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
    const idx = chatroomList.value.findIndex((c) => c.id === chatroomId);
    if (idx !== -1) {
        chatroomList.value.splice(idx, 1);
    }
}

const initWebSocket = (retries = 10) => {
    try {
        const wsClient = (window as unknown as { WebSocketClient?: new () => WebSocketClient }).WebSocketClient;
        if (typeof wsClient === 'undefined') {
            if (retries > 0) {
                setTimeout(() => initWebSocket(retries - 1), 500);
            }
            return;
        }

        const socketClient = new wsClient();
        socketClient.onmessage = (msg: ChatroomMessage) => {
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
                    // Unknown message type
            }
        };
        socketClient.open('chatrooms');
    }
    catch {
        // Failed to initialize WebSocket
    }
};

onMounted(() => {
    initWebSocket();
});
</script>

<template>
  <div class="content">
    <div class="chatroom-list-header">
      <h1>Live Chat</h1>
      <a
        v-if="userAdmin"
        href="javascript:void(0)"
        data-testid="new-chatroom-btn"
        class="btn btn-primary"
        @click="openCreateModal"
      >New Chatroom</a>
    </div>
    <hr>
    <div class="chatrooms-table-wrapper table-responsive">
      <h2>Chatrooms</h2>
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
              @edit="openEditModal"
              @delete="onDeleteChatroom"
              @clear="onClearChatroom"
              @toggle-chatroom="onToggleChatroom"
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
            />
          </tbody>
        </template>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <ChatroomFormModal
      :visible="modalVisible"
      :mode="modalMode"
      :chatroom="editingChatroom"
      @close="closeModal"
      @save="onSaveForm"
    />
  </div>
</template>

<style scoped>
.chatroom-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
</style>
