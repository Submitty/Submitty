<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue';

const props = defineProps<{
    semester: string;
    course: string;
    gradeableId: string;
    userGroup: number;
    blindStatus: number;
    courseUrl: string;
}>();

const emit = defineEmits<{
    cancel: [url: string];
}>();

const storageKey = `${props.semester}-${props.course}-${props.gradeableId}-message`;
const isGrader = props.userGroup === 2 || props.userGroup === 3;
const isPeer = props.userGroup === 4;
const isInstructor = props.userGroup === 1;

const visible = ref(false);
const canAgree = ref(true);

if (isInstructor || localStorage.getItem(storageKey) === 'agreed') {
    canAgree.value = false;
}
else {
    visible.value = true;
}

const popupRef = ref<HTMLElement | null>(null);

onMounted(async () => {
    if (visible.value) {
        await nextTick();
        popupRef.value?.focus();
    }
});

function showReview(): void {
    canAgree.value = false;
    visible.value = true;
    void nextTick(() => {
        popupRef.value?.focus();
    });
}

function agree(): void {
    localStorage.setItem(storageKey, 'agreed');
    canAgree.value = false;
    visible.value = false;
}

function cancel(): void {
    visible.value = false;
    emit('cancel', props.courseUrl);
}

function close(): void {
    visible.value = false;
}
</script>

<template>
  <div>
    <button
      v-if="!visible && !isInstructor"
      class="btn btn-default"
      data-testid="grader-responsibility"
      type="button"
      @click="showReview"
    >
      Responsibilities as a Grader
    </button>
    <div
      v-if="visible"
      id="gradeable-message-popup"
      ref="popupRef"
      class="popup-form"
      tabindex="-1"
      @keydown.escape="close"
    >
      <div
        class="popup-box"
        @click="close"
      >
        <div
          class="popup-window"
          data-testid="popup-window"
          @click.stop
        >
          <div class="form-title">
            <h1>Your Responsibility as a Grader</h1>
            <button
              class="btn btn-default close-button"
              data-testid="close-button"
              tabindex="-1"
              type="button"
              @click="close"
            >
              Close
            </button>
          </div>
          <div class="form-body">
            <div
              v-if="isGrader"
              class="content gradeable_message"
            >
              <p>
                You will be reviewing materials submitted by students and these materials are considered confidential.<br>
                The author of this material is entitled to copyright protection. As such,
              </p>
              <ul>
                <li>You may not use or share any of these materials or ideas without explicit permission from the author.</li>
                <li>
                  You may not retain this material.<br>
                  You must delete/destroy any copies of this material after your review and grading of the work is complete.
                </li>
                <li v-if="blindStatus === 3">
                  Your instructor has configured the grading for this assignment to be <em>blinded</em>.<br>
                  You should not attempt to discover the identities of the students.<br>
                  However, their identities may be unintentionally or unavoidably revealed.<br>
                  Please respect the students' privacy.
                </li>
              </ul>
              <p>
                Please communicate with the instructor if you realize you have a conflict of interest with a student that you have been assigned to grade.
              </p>
            </div>
            <div
              v-else-if="isPeer"
              class="content gradeable_message"
            >
              <p>
                You will be reviewing materials submitted by your classmates and these materials are considered confidential.<br>
                Your classmate, the author of this material, is entitled to copyright protection. As such,
              </p>
              <ul>
                <li>You may not use or share any of these materials or ideas without explicit permission from the author.</li>
                <li>You may not retain this material -- you must delete/destroy any copies of this material after your review and grading of the work is complete.</li>
                <li v-if="blindStatus === 3">
                  Your instructor has configured the grading for this assignment to be double-blind.<br>
                  You should not attempt to discover the identities of your classmates.<br>
                  However, the identities of the classmates may be unintentionally or unavoidably revealed.<br>
                  Please respect your classmates' privacy.
                </li>
                <li v-else-if="blindStatus === 2">
                  Your instructor has configured the grading for this assignment to be single-blind.
                </li>
                <li v-else>
                  Your instructor has configured the grading for this assignment to be unblinded.
                </li>
              </ul>
              <p>
                Please communicate with your instructor if you realize you have a conflict of interest with a peer that you have been assigned to grade.
              </p>
            </div>
            <div
              class="form-buttons"
            >
              <div class="form-button-container">
                <button
                  v-if="canAgree"
                  id="cancel-button"
                  class="btn btn-default close-button"
                  type="button"
                  @click="cancel"
                >
                  Cancel
                </button>
                <button
                  v-if="canAgree"
                  id="agree-button"
                  class="btn btn-primary"
                  type="button"
                  data-testid="agree-popup-btn"
                  @click="agree"
                >
                  Agree
                </button>
                <button
                  class="btn btn-default close-button"
                  type="button"
                  data-testid="close-hidden-button"
                  @click="close"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
