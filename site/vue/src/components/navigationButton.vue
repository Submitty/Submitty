<script setup lang="ts">
defineProps<{
    onClick: () => void;
    icons: string[];
    buttonId: string;
    optionalHref?: string;
    optionalTestId?: string;
    optionalSpanid?: string;
    title: string;
}>();
</script>

<template>
  <span
    :id="optionalSpanid"
    class="ta-navlink-cont"
    :data-testid="optionalTestId"
  >
    <button
      :id="buttonId"
      class="invisible-btn"
      :data-href="optionalHref"
      :title="title"
      @click="onClick"
    >
      <i
        v-for="icon in icons"
        :key="icon"
        :class="`fas ${icon} icon-header icon-streched`"
      />
    </button>
  </span>
</template>
