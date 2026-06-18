<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    onClick: () => void;
    visibleIcon: string;
    hiddenIcon?: string;
    displayHidden?: boolean;
    buttonId: string;
    optionalHref?: string;
    optionalTestId?: string;
    optionalSpanid?: string;
    title: string;
}>();

function handleClick() {
    // If a hidden icon is provided, toggle its visibility
    if (props.hiddenIcon) {
        displayHidden.value = !displayHidden.value;
    }
    props.onClick();
}

const displayHidden = ref(props.displayHidden || false);
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
      @click="handleClick"
    >
      <i
        :class="`fas ${displayHidden ? hiddenIcon : visibleIcon} icon-header icon-streched`"
      />
    </button>
  </span>
</template>
