<script setup lang="ts">
type WindowWithLikeHandlers = Window & {
    toggleLike?: (postId: number, threadId: number, currentUser: string) => void;
    showUpduckUsers?: (postId: number) => void;
};

interface Props {
    postId: number;
    threadId: number;
    currentUser: string;
    userLiked: boolean;
    likeCount: number;
    likedByStaff: boolean;
    showLikersIcon: boolean;
}

const props = defineProps<Props>();
const likeWindow = window as WindowWithLikeHandlers;

const handleToggleLike = () => {
    likeWindow.toggleLike?.(props.postId, props.threadId, props.currentUser);
};

const handleShowLikedUsers = () => {
    likeWindow.showUpduckUsers?.(props.postId);
};
</script>

<template>
  <span
    class="upduck-container"
    data-testid="upduck-container"
  >
    <button
      data-testid="upduck-button"
      class="upduck-button text-decoration-none"
      tabindex="0"
      title="Like the post"
      @click="handleToggleLike"
    >
      <img
        :id="`likeIcon_${props.postId}`"
        :src="props.userLiked ? '/img/on-duck-button.svg' : '/img/light-mode-off-duck.svg'"
        alt="Like"
        width="30"
        height="30"
      >
    </button>
    <span
      :id="`likeCounter_${props.postId}`"
      data-testid="like-count"
      class="like-counter"
    >{{ props.likeCount }}</span>
    <span
      :id="`likedByInstructor_${props.postId}`"
      data-testid="instructor-like"
      class="liked-by-instructor"
      :style="props.likedByStaff ? '' : 'display: none;'"
    >
      liked by teaching staff
    </span>
    <i
      v-if="props.showLikersIcon"
      class="fas fa-users icon-mark-stats"
      data-testid="show-upduck-list"
      title="See who liked this post"
      style="cursor: pointer; margin-left: 10px;"
      @click="handleShowLikedUsers"
    />
  </span>
</template>
