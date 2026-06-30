<script setup lang="ts">
const props = defineProps<{
    randomizeUrl: string;
}>();

const emit = defineEmits<{
    cancel: [];
    confirm: [url: string];
}>();

function cancel() {
    emit('cancel');
}

function confirm() {
    emit('confirm', props.randomizeUrl);
}
</script>

<template>
  <div
    id="randomize-button-warning"
    class="popup-form"
    style="display: none;"
  >
    <div
      class="popup-box"
      @click="cancel"
    >
      <div
        class="popup-window"
        @click.stop
      >
        <div class="form-title">
          <h1>WARNING: Grading may be in progress!</h1>
          <button
            class="btn btn-default close-button"
            type="button"
            @click="cancel"
          >
            Close
          </button>
        </div>
        <div class="form-body">
          <p>
            Updating the sections for this gradeable will change the
            submissions assigned to each grader.<br />
            If grading has begun, this may interrupt or redistribute grade
            submissions.<br /><br /><br />
            Are you sure you want to continue?<br /><br />
          </p>
        </div>
        <div class="form-buttons">
          <div class="form-button-container">
            <button
              class="btn btn-default close-button randomize_cancel_btn"
              data-testid="randomize-cancel"
              @click="cancel"
            >
              Cancel
            </button>
            <button
              class="btn btn-primary"
              data-testid="randomize-confirm"
              @click="confirm"
            >
              Randomly Re-Assign Teams to Rotating Sections
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
