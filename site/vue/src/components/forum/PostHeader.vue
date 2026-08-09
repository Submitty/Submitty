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

const pinControlClass = props.isAnnounced
    ? (props.isExpiring ? 'active-thread-remove-expiring-announcement' : 'active-thread-remove-announcement')
    : 'not-active-thread-announcement';

const pinControlTitle = props.isAnnounced
    ? (props.isExpiring ? 'Thread is expiring soon click to unpin' : 'Unpin thread')
    : 'Pin thread to the top';

const pinnedIconClass = props.isExpiring ? 'active-thread-announcement-expiring' : 'active-thread-announcement';

const bookmarkClass = props.isFavorite ? 'current-favorite' : 'pinned-thread';
const bookmarkTitle = props.isFavorite ? 'Unbookmark Thread' : 'Bookmark Thread';

function onPinClick() {
    if (props.isAnnounced) {
        emit('unpin-thread', props.threadId);
    }
    else {
        emit('pin-thread', props.threadId);
    }
}

function onBookmarkClick() {
    if (props.isFavorite) {
        emit('unbookmark-thread', props.threadId);
    }
    else {
        emit('bookmark-thread', props.threadId);
    }
}
</script>

<template>
  <span data-testid="post-header">
    <a
      v-if="canPin"
      :class="pinControlClass"
      data-testid="pin-thread-button"
      tabindex="0"
      role="button"
      :title="pinControlTitle"
      :aria-label="pinControlTitle"
      @click="onPinClick"
      @keydown.enter.prevent="onPinClick"
      @keydown.space.prevent="onPinClick"
    >
      <i
        class="fas fa-thumbtack"
        :class="isAnnounced ? '' : 'golden_hover'"
      />
    </a>
    <i
      v-else-if="isAnnounced"
      class="fas fa-thumbtack"
      :class="pinnedIconClass"
      data-testid="pinned-icon"
      title="Pinned Thread"
      aria-label="Pinned Thread"
    />

    <a
      :class="bookmarkClass"
      data-testid="bookmark-thread-button"
      tabindex="0"
      role="button"
      :title="bookmarkTitle"
      :aria-label="bookmarkTitle"
      @click="onBookmarkClick"
      @keydown.enter.prevent="onBookmarkClick"
      @keydown.space.prevent="onBookmarkClick"
    >
      <i
        class="fas fa-bookmark"
        :class="isFavorite ? '' : 'golden_hover red-hover'"
      />
    </a>

    {{ title }}
  </span>
</template>
