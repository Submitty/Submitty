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
    toggle: [ev: MouseEvent | KeyboardEvent];
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
                            emit('toggle', event.originalEvent as KeyboardEvent);
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
  <slot name="trigger">
    <a
      class="btn btn-primary"
      @click="$emit('toggle', $event)"
    >Toggle popup (you should probably override this)</a>
  </slot>
  <div
    v-if="visible"
    class="popup-form"
  >
    <div
      class="popup-box"
      @click="$emit('toggle', $event)"
    >
      <div
        :id="id"
        class="popup-window"
        data-testid="popup-window"
        @click.stop
      >
        <div class="form-title">
          <h1>{{ title }}</h1>
          <button
            class="btn btn-default close-button"
            data-testid="close-button"
            tabindex="0"
            @click="$emit('toggle', $event)"
          >
            Close
          </button>
        </div>

        <div class="form-body">
          <slot>Default popup content (you should probably override this)</slot>

          <div class="form-buttons">
            <div class="form-button-container">
              <button
                class="btn btn-default close-button"
                data-testid="popup-close-button"
                tabindex="0"
                @click="$emit('toggle', $event)"
              >
                {{ dismissText }}
              </button>
              <button
                v-if="savable"
                class="btn btn-primary"
                data-testid="popup-save-button"
                tabindex="1"
                @click="$emit('save', $event)"
              >
                {{ saveText }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
h1 {
  margin-bottom: 0 !important;
}

.popup-window {
  gap: 0.25em;
}

.popup-form {
  box-sizing: border-box;
}
</style>
