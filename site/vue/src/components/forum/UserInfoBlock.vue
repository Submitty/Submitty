<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
    username: string;
    fullName: string;
    isAnonymous: boolean;
    isOP: boolean;
    pronouns: string;
    showPronouns: boolean;
    postDate: string;
    editDate: string | null;
    authorEmail: string;
    showEmailToggle: boolean;
    showUserInfoToggle: boolean;
}

const props = defineProps<Props>();

const emailVisible = ref(false);
const nameRevealed = ref(false);

const displayName = computed(() => (nameRevealed.value ? props.fullName : props.username));
const eyeIconClass = computed(() => (nameRevealed.value ? 'fas fa-eye-slash' : 'fas fa-eye'));
const eyeTitle = computed(() => (nameRevealed.value ? 'Hide full user information' : 'Show full user information'));
const nameClasses = computed(() => ({ 'user-info-anon-revealed': props.isAnonymous && nameRevealed.value }));
</script>

<template>
  <span class="user-info-block">
    <a
      v-if="props.showEmailToggle"
      class="post-email-toggle"
      data-testid="email-toggle"
      tabindex="0"
      title="Show/Hide email address"
      aria-label="Show/Hide email address"
      @click="emailVisible = !emailVisible"
      @keydown.enter.prevent="emailVisible = !emailVisible"
      @keydown.space.prevent="emailVisible = !emailVisible"
    >
      <i class="fas fa-envelope" />
    </a>
    <a
      v-if="props.showEmailToggle"
      v-show="emailVisible"
      :href="`mailto:${props.authorEmail}`"
      data-testid="author-email"
    >
      {{ props.authorEmail }}
    </a>
    <a
      v-if="props.showUserInfoToggle"
      class="post-user-info"
      data-testid="user-info-toggle"
      tabindex="0"
      :title="eyeTitle"
      :aria-label="eyeTitle"
      @click="nameRevealed = !nameRevealed"
      @keydown.enter.prevent="nameRevealed = !nameRevealed"
      @keydown.space.prevent="nameRevealed = !nameRevealed"
    >
      <i
        :class="eyeIconClass"
        data-testid="user-info-toggle-icon"
      />
    </a>
    <span
      class="last-edit"
      data-testid="last-edit"
    >
      <strong
        class="post_user_id"
        data-testid="post-user-id"
        :title="props.isOP ? 'Original Poster' : undefined"
      ><span
        class="author-name"
        data-testid="author-name"
        :class="nameClasses"
      >{{ displayName }}</span> <span
        v-if="props.isOP"
        style="color: var(--standard-medium-vibrant-blue);"
      ><strong>OP</strong></span></strong> <strong
        v-if="props.showPronouns"
        class="post_user_pronouns"
        data-testid="post-user-pronouns"
      >({{ props.pronouns }})</strong> {{ props.postDate }} <template v-if="props.editDate !== null">(<i>Last edit at {{ props.editDate }}</i>)</template>
    </span>
  </span>
</template>

<style scoped>
.user-info-anon-revealed {
    color: var(--standard-medium-gray);
    font-style: italic;
}
</style>
