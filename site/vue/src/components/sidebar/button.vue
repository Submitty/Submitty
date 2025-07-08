<script setup lang="ts">

type Button = {
    title: string;
    href?: string | null;
    class?: string;
    id?: string;
    icon?: string | null;
    badge?: number;
    prefix?: string;
};

const props = defineProps<{
    buttons: Button[];
    mobile?: boolean;
}>();

function getButtonId(button: Button): string | undefined {
    if (!button.id) {
        return undefined;
    }
    return (props.mobile ? 'mobile-' : '') + button.id;
}
</script>

<template>
  <ul>
    <template
      v-for="button in buttons"
      :key="button.title"
    >
      <li v-if="!button.title || (mobile && button.title === 'Collapse Sidebar')">
        <hr />
      </li>
      <li v-else>
        <span
          v-if="!button.href"
          :id="getButtonId(button)"
          class="flex-row"
          :class="[button.class]"
        >
          <span class="flex-line">
            <i
              v-if="button.icon"
              class="fa"
              :class="[button.icon]"
            />
            {{ button.title }}
          </span>
          <span
            v-if="button.badge && button.badge > 0"
            class="notification-badge"
          >
            {{ button.badge }}
          </span>
        </span>
        <a
          v-else
          :id="getButtonId(button)"
          :href="button.href"
          :title="button.title"
          class="flex-row"
          :class="[button.class]"
          :data-toggle="!mobile ? 'tooltip' : undefined"
        >
          <span class="flex-line">
            <i
              v-if="button.icon"
              :class="[button.prefix, button.icon]"
            />
            <span class="icon-title">{{ button.title }}</span>
          </span>
          <span
            v-if="button.badge && button.badge > 0"
            class="notification-badge"
          >
            {{ button.badge }}
          </span>
        </a>
      </li>
    </template>
  </ul>
</template>
<style scoped>
.flex-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.flex-line {
    display: flex;
    align-items: center;
}

.notification-badge {
    background-color: var(--danger-red);
    padding: 1px 5px;
    color: white;
    font-weight: bold;
    border-radius: 2px;
}
</style>
