<script setup lang="ts">
import { ref } from 'vue';
import Popup from './Popup.vue';

const props = defineProps<{
    storageKey: string;
    userGroup: number;
    blindStatus: number;
}>();

const emit = defineEmits<{
    cancel: [];
}>();

const isGrader = props.userGroup === 2 || props.userGroup === 3;
const isPeer = props.userGroup === 4;
const isInstructor = props.userGroup === 1;

const visible = ref(false);
const canAgree = ref(true);

if (isInstructor || localStorage.getItem(props.storageKey) === 'agreed') {
    canAgree.value = false;
}
else {
    visible.value = true;
}

function showReview(): void {
    canAgree.value = false;
    visible.value = true;
}

function agree(): void {
    localStorage.setItem(props.storageKey, 'agreed');
    canAgree.value = false;
    visible.value = false;
}

function cancel(): void {
    visible.value = false;
    emit('cancel');
}

function close(): void {
    visible.value = false;
}
</script>

<template>
  <Popup
    :visible="visible"
    title="Your Responsibility as a Grader"
    no-footer
    @toggle="close"
  >
    <template #trigger>
      <button
        v-if="!isInstructor"
        class="btn btn-default"
        data-testid="grader-responsibility"
        type="button"
        @click="showReview"
      >
        Responsibilities as a Grader
      </button>
    </template>
    <div class="gradeable-message-content">
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
            Please respect your students' privacy.
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
      <div class="form-buttons">
        <div class="form-button-container">
          <template v-if="canAgree">
            <button
              id="cancel-button"
              class="btn btn-default close-button"
              type="button"
              @click="cancel"
            >
              Cancel
            </button>
            <button
              id="agree-button"
              class="btn btn-primary"
              type="button"
              data-testid="agree-popup-btn"
              @click="agree"
            >
              Agree
            </button>
          </template>
          <button
            v-else
            id="close-hidden-button"
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
  </Popup>
</template>
