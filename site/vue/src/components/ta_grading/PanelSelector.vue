<script setup lang="ts">
import { onMounted, ref } from 'vue';

const props = defineProps<{
    optionId: string;
    label: string;
    draw: (ctx: CanvasRenderingContext2D) => void;
    testid?: string;
}>();

defineEmits<{
    select: [];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);

onMounted(() => {
    const ctx = canvasRef.value?.getContext('2d');
    if (ctx) {
        props.draw(ctx);
    }
});
</script>

<template>
  <div class="layout-option-item">
    <canvas
      :id="optionId"
      ref="canvasRef"
    />
    <div class="flex-col">
      <span>{{ label }}</span>
      <button
        type="button"
        class="btn btn-primary"
        :data-testid="testid ?? undefined"
        @click="$emit('select')"
      >
        Apply
      </button>
    </div>
  </div>
</template>
