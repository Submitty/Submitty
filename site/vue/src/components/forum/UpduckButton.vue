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
const emit = defineEmits<{
    'toggle-like': [payload: { postId: number; threadId: number; currentUser: string }];
    'show-liked-users': [payload: { postId: number }];
}>();

const handleToggleLike = () => {
    emit('toggle-like', { postId: props.postId, threadId: props.threadId, currentUser: props.currentUser });
};

const handleShowLikedUsers = () => {
    emit('show-liked-users', { postId: props.postId });
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
      v-show="props.likedByStaff"
      :id="`likedByInstructor_${props.postId}`"
      data-testid="instructor-like"
      class="liked-by-instructor"
    >
      liked by teaching staff
    </span>
    <i
      v-if="props.showLikersIcon"
      class="fas fa-users icon-mark-stats"
      data-testid="show-upduck-list"
      title="See who liked this post"
      @click="handleShowLikedUsers"
    />
  </span>
</template>

<style scoped>
.icon-mark-stats {
    margin-left: 10px;
}
</style>
