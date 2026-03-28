<template>
  <div 
    :class="['submitty-alert', `alert-${type}`]" 
    role="alert"
    aria-live="polite"
  >
    <!-- Icon Container aligned to start to handle long text wrapping -->
    <div class="alert-icon-wrapper flex items-start mt-1">
      <!-- Accessible Icon with explicit shape variation for colorblindness -->
      <div class="icon-circle flex items-center justify-center rounded-full p-2.5 mr-4 shadow-sm">
        <component :is="iconComponent" class="w-8 h-8 flex-shrink-0" aria-hidden="true" />
      </div>
    </div>
    
    <div class="alert-content flex-grow flex flex-col justify-center">
      <!-- Explicit Textual Prefix for extreme accessibility/colorblind support -->
      <span class="text-xs font-bold uppercase tracking-wider mb-1 opacity-80 alert-type-label">
        {{ type }}
      </span>
      <div class="alert-body text-sm md:text-base leading-relaxed">
        <slot></slot>
      </div>
    </div>

    <!-- Close button -->
    <button aria-label="Dismiss alert" @click="$emit('close')" class="close-btn ml-4 mt-1 flex-shrink-0 opacity-50 hover:opacity-100 transition-all duration-200 p-1 rounded-full hover:bg-black/5 dark:hover:bg-white/10">
      <X class="w-5 h-5" />
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Info, CheckCircle, AlertTriangle, XCircle, X } from 'lucide-vue-next';

const props = defineProps({
  type: { 
    type: String, 
    default: 'info',
    validator: (value) => ['info', 'success', 'warning', 'error'].includes(value)
  }
});

defineEmits(['close']);

const iconComponent = computed(() => {
  const icons = {
    info: Info,
    success: CheckCircle,
    warning: AlertTriangle,
    error: XCircle
  };
  return icons[props.type];
});
</script>
