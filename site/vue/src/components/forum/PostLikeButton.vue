<script setup lang="ts">
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

const handleToggleLike = () => {
    if (typeof window.toggleLike === 'function') {
        window.toggleLike(props.postId, props.threadId, props.currentUser);
    }
};

const handleShowLikedUsers = () => {
    if (typeof window.showUpduckUsers === 'function') {
        window.showUpduckUsers(props.postId);
    }
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
      data-testid="like-count"
      :id="`likeCounter_${props.postId}`"
      class="like-counter"
    >{{ props.likeCount }}</span>
    <span
      data-testid="instructor-like"
      :id="`likedByInstructor_${props.postId}`"
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
