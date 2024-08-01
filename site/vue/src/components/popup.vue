<script setup lang="ts">
import { watch } from 'vue';

const props = withDefaults(defineProps<{
    id?: string;
    visible?: boolean;
    closeButton?: {
        id: string;
        classes: string[];
    };
}>(), {
    id: 'popup',
    title: 'popup title',
    closeButton() {
        return {
            id: 'close_button',
            classes: ['btn-default'],
        };
    },

});

const emit = defineEmits<{
    dismiss: [];
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
                            emit('dismiss');
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
    :id="id"
    class="popup-form"
  >
    <div
      class="popup-box"
      @click="$emit('dismiss')"
    >
      <div
        class="popup-window"
        @click.prevent
      >
        <slot name="title_panel">
          <div class="form-title">
            <slot name="title_tag">
              <h1>
                <slot name="title">
                  Untitled Form
                </slot>
              </h1>
            </slot>
            <button
              data-testid="close-button"
              class="btn btn-default close-button"
              tabindex="-1"
              type="button"
              @click="$emit('dismiss')"
            >
              Close
            </button>
          </div>
        </slot>
        <slot name="body_panel">
          <div class="form-body">
            <slot name="body">
              Be sure to override the body slot.
            </slot>
            <slot name="buttons_panel">
              <div class="form-buttons">
                <div class="form-button-container">
                  <slot name="buttons">
                    <slot name="close_button">
                      <a
                        :id="closeButton.id"
                        class="btn close-button key_to_click"
                        :class="closeButton.classes.join(' ')"
                        tabindex="0"
                        @click="$emit('dismiss')"
                      >
                        <slot name="close_button_text">
                          Close
                        </slot>
                      </a>
                    </slot>
                  </slot>
                </div>
              </div>
            </slot>
          </div>
        </slot>
      </div>
    </div>
  </div>
</template>
