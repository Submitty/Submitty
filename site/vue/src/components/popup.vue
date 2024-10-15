<script setup lang="ts">
import { watch } from 'vue';

const props = withDefaults(defineProps<{
    /** The id of the displayed popup, for styling purposes */
    id?: string;
    /** The title text of the popup */
    title: string;
    /** Whether to show the popup or not */
    visible: boolean;
    /** Whether the popup should show 'discard' and 'save' options or just one 'close' option */
    savable?: boolean;
    /** The text to show for the close/discard button */
    dismissText?: string;
    /** The text to show for the save button */
    saveText?: string;
}>(), {
    id: 'popup',
    dismissText: (props) => props.savable ? 'Discard' : 'Close',
    saveText: 'Save',
});

const emit = defineEmits<{
    /** The user clicked out of the popup, or hit cancel/discard  */
    dismiss: [ev: MouseEvent | KeyboardEvent];
    /** The user clicked the save button  */
    save: [ev: MouseEvent | KeyboardEvent];
}>();

watch(
    () => props.visible,
    (visible, old_visible) => {
        if (old_visible !== visible) {
            if (visible) {
                $(document).on(
                    'keydown.popup',
                    (event) => {
                        if (event.key === 'Escape' && props.visible) {
                            emit('dismiss', event.originalEvent as KeyboardEvent);
                        }
                    });
            }
            else {
                $(document).off('keydown.popup');
            }
        }
    },
    { immediate: true },
);

</script>

<template>
  <div
    v-if="visible"
    class="popup-form"
  >
    <div
      class="popup-box"
      @click="$emit('dismiss', $event)"
    >
      <div
        :id="id"
        class="popup-window d-flex flex-col"
        data-testid="popup-window"
        @click.stop
      >
        <div class="form-title d-flex justify-content-space-between">
          <h1>{{ title }}</h1>
          <div>
            <button
              class="btn close-button"
              data-testid="close-button"
              tabindex="0"
              @click="$emit('dismiss', $event)"
            >
              {{ dismissText }}
            </button>
            <button
              v-if="savable"
              class="btn btn-primary"
              data-testid="save-button"
              tabindex="1"
              @click="$emit('save', $event)"
            >
              {{ saveText }}
            </button>
          </div>
        </div>

        <slot>Default popup content (you should probably override this)</slot>

        <div class="form-button-container">
          <button
            v-if="savable"
            class="btn btn-primary"
            data-testid="save-button"
            tabindex="1"
            @click="$emit('save', $event)"
          >
            {{ saveText }}
          </button>
          <button
            v-else
            class="btn close-button"
            data-testid="close-button"
            tabindex="0"
            @click="$emit('dismiss', $event)"
          >
            {{ dismissText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
h1 {
  margin-bottom: 0 !important;
}

.popup-window {
  gap: 0.25em;
}
</style>
