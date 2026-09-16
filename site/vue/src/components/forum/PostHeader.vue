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

const pinClass = !props.canPin
    ? (props.isExpiring ? 'active-thread-announcement-expiring' : 'active-thread-announcement')
    : (props.isAnnounced
            ? (props.isExpiring ? 'active-thread-remove-expiring-announcement' : 'active-thread-remove-announcement')
            : 'not-active-thread-announcement');

const pinTitle = !props.canPin
    ? 'Pinned Thread'
    : (props.isAnnounced
            ? (props.isExpiring ? 'Thread is expiring soon click to unpin' : 'Unpin thread')
            : 'Pin thread to the top');

const bookmarkClass = props.isFavorite ? 'current-favorite' : 'pinned-thread';
const bookmarkTitle = props.isFavorite ? 'Unbookmark Thread' : 'Bookmark Thread';

function onPinClick() {
    if (!props.canPin) {
        return;
    }
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
  <div
    class="create-post-head"
    data-testid="create-post-head"
  >
    <a
      v-if="canPin || isAnnounced"
      :class="pinClass"
      data-testid="pin-thread-button"
      tabindex="0"
      role="button"
      :title="pinTitle"
      :aria-label="pinTitle"
      @click="onPinClick"
      @keydown.enter.prevent="onPinClick"
      @keydown.space.prevent="onPinClick"
    >
      <i
        class="fas fa-thumbtack"
        :class="(!canPin || isAnnounced) ? '' : 'golden_hover'"
      />
    </a>

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

    <h2>{{ title }}</h2>
  </div>
</template>
