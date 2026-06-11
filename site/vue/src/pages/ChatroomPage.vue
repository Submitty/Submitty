<script setup lang="ts">
import { ref, onMounted, nextTick, computed } from 'vue';
import ChatMessage from '@/components/chat/ChatMessage.vue';

/* global WebSocketClient, buildCourseUrl, csrfToken, displayErrorMessage */

const props = defineProps<{
    chatroomId: string | number;
    chatroomTitle: string;
    baseUrl: string;
    csrfToken: string;
    userId: string;
    displayName: string;
    userAdmin: boolean;
    isAnonymous: boolean;
    readOnly: boolean;
}>();

const messages = ref<any[]>([]);
const newMessage = ref('');
const isReadOnly = ref(props.readOnly);
const messagesArea = ref<HTMLElement | null>(null);
const showToast = ref(false);
const toastMessage = ref('');

const fetchMessages = async () => {
    try {
        const response = await fetch(`${props.baseUrl}/${props.chatroomId}/messages`);
        const result = await response.json();
        if (result.status === 'success' && Array.isArray(result.data)) {
            messages.value = result.data;
            await nextTick();
            scrollToBottom(true);
        }
    }
    catch (e) {
        (window as any).alert('Something went wrong with fetching messages');
    }
};

const sendMessage = async () => {
    const trimmedMessage = newMessage.value.trim();
    if (trimmedMessage === '') {
        (window as any).alert('Please enter a message.');
        return;
    }

    const role = props.userAdmin ? 'instructor' : 'student';
    const toBuild = props.isAnonymous ? `${props.baseUrl}/${props.chatroomId}/send/anonymous` : `${props.baseUrl}/${props.chatroomId}/send`;

    const formData = new FormData();
    formData.append('csrf_token', props.csrfToken);
    formData.append('user_id', props.userId);
    formData.append('display_name', props.displayName);
    formData.append('role', role);
    formData.append('content', trimmedMessage);

    try {
        const response = await fetch(toBuild, {
            method: 'POST',
            body: formData,
        });
        const result = await response.json();
        if (result.status !== 'success') {
            (window as any).displayErrorMessage(result.message);
            return;
        }
        newMessage.value = '';
    }
    catch (e) {
        (window as any).alert('Something went wrong with storing message');
    }
};

const scrollToBottom = (force = false) => {
    if (!messagesArea.value) {
        return;
    }
    const area = messagesArea.value;
    const distanceFromBottom = area.scrollHeight - area.scrollTop - area.clientHeight;
    if (force || distanceFromBottom < 110) {
        area.scrollTop = area.scrollHeight;
    }
};

const socketClient = ref<any>(null);

const initWebSocket = (retries = 10) => {
    try {
        if (typeof (window as any).WebSocketClient === 'undefined') {
            if (retries > 0) {
                setTimeout(() => initWebSocket(retries - 1), 500);
            } else {
            }
            return;
        }

        socketClient.value = new (window as any).WebSocketClient();
        (window as any).socketClient = socketClient.value; // For debugging and compatibility

        socketClient.value.onmessage = (msg: any) => {
            switch (msg.type) {
                case 'chat_message':
                    messages.value.push({
                        id: msg.message_id,
                        display_name: msg.display_name,
                        role: msg.role,
                        timestamp: msg.timestamp,
                        content: msg.content,
                    });
                    nextTick(() => scrollToBottom());
                    break;
                case 'chat_close':
                    if (msg.allow_read_only_after_end) {
                        isReadOnly.value = true;
                    }
                    else {
                        (window as any).alert('Chatroom has been closed by the instructor.');
                        window.location.href = props.baseUrl;
                    }
                    break;
                case 'message_delete':
                    messages.value = messages.value.filter((m) => m.id.toString() !== msg.message_id.toString());
                    break;
            }
        };

        socketClient.value.onopen = () => {
        };

        socketClient.value.open('chatrooms', {
            chatroom_id: props.chatroomId,
        });
    } catch (err) {
    }
};

const triggerToast = (msg: string) => {
    toastMessage.value = msg;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 4000);
};

const handleKeypress = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
};

onMounted(() => {
    triggerToast(`You have successfully joined as ${props.displayName}.`);
    initWebSocket();
    fetchMessages();
});

const truncatedTitle = computed(() => {
    return props.chatroomTitle.length > 40 ? `${props.chatroomTitle.slice(0, 40)}...` : props.chatroomTitle;
});
</script>

<template>
    <div
        id="socket-server-system-message"
        style="display: none;"
    />
    <div
        v-if="showToast"
        class="chatroom-toast"
        style="visibility: visible; opacity: 0.85"
    >
        {{ toastMessage }}
    </div>
    <div class="content chatroom-page dim-overlay">
        <div class="chatroom-container">
            <div class="chatroom-header">
                <span
                    class="chatroom-title"
                    data-testid="chat-title"
                    :title="chatroomTitle"
                >
                    {{ truncatedTitle }}
                </span>
                <a
                    :href="baseUrl"
                    class="leave-room btn btn-danger"
                    data-testid="leave-chat"
                >
                    Leave
                </a>
            </div>
            <div class="chatroom-content">
                <div
                    ref="messagesArea"
                    class="messages-area"
                >
                    <ChatMessage
                        v-for="message in messages"
                        :id="message.id"
                        :key="message.id"
                        :display-name="message.display_name"
                        :role="message.role"
                        :timestamp="message.timestamp"
                        :content="message.content"
                    />
                </div>
                <div
                    v-if="!isReadOnly"
                    class="input-container"
                >
                    <textarea
                        v-model="newMessage"
                        name="content"
                        class="message-input"
                        placeholder="Enter your message here"
                        maxlength="1500"
                        data-testid="msg-input"
                        @keypress="handleKeypress"
                    />
                    <button
                        class="send-message-btn btn btn-primary"
                        data-testid="send-btn"
                        @click="sendMessage"
                    >
                        Send
                    </button>
                </div>
                <div
                    v-else
                    class="input-container"
                >
                    <textarea
                        class="message-input"
                        disabled
                        placeholder="This chat session has ended. Messages are read-only."
                    />
                    <button
                        class="send-message-btn btn btn-primary"
                        disabled
                    >
                        Send
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Maintain compatibility with global chatroom.css */
.chatroom-toast {
    transition: opacity 1s;
}
</style>
