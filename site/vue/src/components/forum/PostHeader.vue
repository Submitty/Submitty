<script setup lang="ts">
interface Props {
    threadId: number;
    title: string;
    isAnnounced: boolean;
    isExpiring: boolean;
    canPin: boolean;
    isFavorite: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'pin-thread': [threadId: number];
    'unpin-thread': [threadId: number];
    'bookmark-thread': [threadId: number];
    'unbookmark-thread': [threadId: number];
}>();

function emitPin() {
    emit('pin-thread', props.threadId);
}
function emitUnpin() {
    emit('unpin-thread', props.threadId);
}
function emitBookmark() {
    emit('bookmark-thread', props.threadId);
}
function emitUnbookmark() {
    emit('unbookmark-thread', props.threadId);
}
</script>

<template>
  <span data-testid="post-header">
    <a
      v-if="props.canPin && props.isAnnounced"
      :class="props.isExpiring ? 'active-thread-remove-expiring-announcement' : 'active-thread-remove-announcement'"
      data-testid="unpin-thread-button"
      tabindex="0"
      :title="props.isExpiring ? 'Thread is expiring soon click to unpin' : 'Unpin thread'"
      :aria-label="props.isExpiring ? 'Thread is expiring soon click to unpin' : 'Unpin thread'"
      @click="emitUnpin"
      @keydown.enter.prevent="emitUnpin"
      @keydown.space.prevent="emitUnpin"
    >
      <i class="fas fa-thumbtack" />
    </a>
    <i
      v-else-if="props.isAnnounced"
      class="fas fa-thumbtack"
      :class="props.isExpiring ? 'active-thread-announcement-expiring' : 'active-thread-announcement'"
      data-testid="pinned-icon"
      title="Pinned Thread"
      aria-label="Pinned Thread"
    />
    <a
      v-else-if="props.canPin"
      class="not-active-thread-announcement"
      data-testid="pin-thread-button"
      tabindex="0"
      title="Pin thread to the top"
      aria-label="Pin thread to the top"
      @click="emitPin"
      @keydown.enter.prevent="emitPin"
      @keydown.space.prevent="emitPin"
    >
      <i class="fas fa-thumbtack golden_hover" />
    </a>

    <a
      v-if="props.isFavorite"
      class="current-favorite"
      data-testid="unbookmark-thread-button"
      tabindex="0"
      title="Unbookmark Thread"
      aria-label="Unbookmark Thread"
      @click="emitUnbookmark"
      @keydown.enter.prevent="emitUnbookmark"
      @keydown.space.prevent="emitUnbookmark"
    >
      <i class="fas fa-bookmark" />
    </a>
    <a
      v-else
      class="pinned-thread"
      data-testid="bookmark-thread-button"
      tabindex="0"
      title="Bookmark Thread"
      aria-label="Bookmark Thread"
      @click="emitBookmark"
      @keydown.enter.prevent="emitBookmark"
      @keydown.space.prevent="emitBookmark"
    >
      <i class="fas fa-bookmark golden_hover red-hover" />
    </a>

    {{ props.title }}
  </span>
</template>
