<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    id: string | number;
    displayName: string;
    role: string | null;
    timestamp: string;
    content: string;
}>();

const formattedName = computed(() => {
    let name = props.displayName;
    if (props.role && props.role !== 'student' && !name.startsWith('Anonymous')) {
        name = `${props.displayName} [${props.role}]`;
    }
    return name;
});

const isInstructor = computed(() => props.role === 'instructor');
</script>

<template>
    <div
        :id="id.toString()"
        class="message-container"
        :class="{ 'admin-message': isInstructor }"
        data-testid="message-container"
    >
        <div
            class="message-header"
            data-testid="message-header"
        >
            <span
                class="sender-name"
                data-testid="sender-name"
            >
                {{ formattedName }}
            </span>
            <span class="timestamp">{{ timestamp }}</span>
        </div>
        <div
            class="message-content"
            data-testid="message-content"
        >
            {{ content }}
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles can be added here if needed, 
   but we're relying on global chatroom.css as per plan */
</style>
